<?php

namespace App\Filament\Resources\Factures\Actions;

use AmjadIqbal\FilamentRecordPdf\Actions\DownloadPdfAction;
use AmjadIqbal\FilamentRecordPdf\Actions\PreviewPdfAction;
use AmjadIqbal\FilamentRecordPdf\Actions\SavePdfAction;
use App\Models\Facture;
use App\Models\RegularisationStock;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FacturePdfActions
{
    private const VUE = 'pdf.facture';

    /**
     * Données de la vue. $emission = true : le PDF généré porte déjà le tampon « ÉMISE »
     * (le statut en base est mis à jour juste après la sauvegarde).
     */
    public static function data(Facture $facture, bool $emission = false): array
    {
        $emet = $emission && $facture->statut === Facture::STATUT_BROUILLON;

        $lignes = $facture->lignes()
            ->with('article.marque')
            // une facture annulée montre ses lignes annulées, sinon on les masque
            ->when(
                $facture->statut !== Facture::STATUT_ANNULEE,
                fn ($q) => $q->where('statut', '!=', RegularisationStock::STATUT_ANNULE)
            )
            ->orderBy('parkod')
            ->get();

        return [
            'statutPdf'   => $emet ? Facture::STATUT_EMISE : $facture->statut,
            'dateFacture' => $emet ? now() : $facture->date_facture,
            'lignes'      => $lignes,
        ];
    }

    public static function apercu(): PreviewPdfAction
    {
        return PreviewPdfAction::make()
            ->label('Aperçu PDF')
            ->icon(null)
            ->pdfView(self::VUE)
            ->pdfData(fn (Facture $record) => self::data($record))
            ->pdfFilename(fn (Facture $record) => "Facture {$record->numero}");
    }

    public static function telecharger(): DownloadPdfAction
    {
        return DownloadPdfAction::make()
            ->label('Télécharger')
            ->icon(null)
            ->pdfView(self::VUE)
            ->pdfData(fn (Facture $record) => self::data($record))
            ->pdfFilename(fn (Facture $record) => "Facture {$record->numero}");
    }

    /**
     * Génère le PDF maintenant, le range sur le disque, enregistre pdf_path,
     * puis émet la facture (BROUILLON -> EMISE) et passe ses lignes à FACTURE.
     */
    public static function generer(): SavePdfAction
    {
        return SavePdfAction::make()
            ->label('Facturer')
            ->icon(null)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Facturer')
            ->modalSubmitActionLabel('Facturer')
            ->modalDescription(fn (Facture $record) => $record->statut === Facture::STATUT_BROUILLON
                ? 'La facture sera émise : statut « Émise », date du jour, lignes passées à « Facturé ».'
                : 'Le PDF existant sera remplacé.')
            ->visible(fn (Facture $record) => $record->statut !== Facture::STATUT_ANNULEE)
            ->pdfView(self::VUE)
            ->pdfData(fn (Facture $record) => self::data($record, emission: true))
            ->pdfFilename(fn (Facture $record) => "Facture {$record->numero}")
            ->disk('local')
            ->directory(fn (Facture $record) => 'factures/' . substr($record->periode, 0, 4))
            ->saveToColumn('pdf_path')
            ->overwrite(true)
            ->afterSaving(function (Facture $record, string $path, string $disk): void {
                DB::transaction(function () use ($record) {
                    if ($record->statut === Facture::STATUT_BROUILLON) {
                        $record->statut       = Facture::STATUT_EMISE;
                        $record->date_facture = now()->toDateString();
                        $record->save();

                        $record->lignes()
                            ->where('statut', RegularisationStock::STATUT_A_FACTURER)
                            ->update(['statut' => RegularisationStock::STATUT_FACTURE]);
                    }
                });

                Notification::make()
                    ->title('Facture émise')
                    ->body('Vous pouvez maintenant la télécharger.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Télécharge le PDF déjà généré par « Facturer » (visible seulement s'il existe).
     */
    public static function telechargerFacture(): Action
    {
        return Action::make('telechargerFacture')
            ->label('Télécharger la facture')
            ->color('primary')
            ->visible(fn (Facture $record) => filled($record->pdf_path)
                && Storage::disk('local')->exists($record->pdf_path))
            ->action(fn (Facture $record) => Storage::disk('local')->download(
                $record->pdf_path,
                "Facture {$record->numero}.pdf",
            ));
    }
}
