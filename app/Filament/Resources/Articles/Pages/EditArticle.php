<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
