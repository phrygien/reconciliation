<?php

return [

    /*
     * The spatie/laravel-pdf driver used to render. "dompdf" is pure PHP and works on any host.
     * Others ("browsershot", "chrome", "gotenberg", "cloudflare", "weasyprint") need their own
     * package or service; see the spatie/laravel-pdf docs. Each action can override it with ->pdfDriver().
     */
    'driver' => env('RECORD_PDF_DRIVER', 'dompdf'),

    /*
     * Paper format and orientation used when an action does not set its own.
     */
    'format' => 'a4',

    'landscape' => false,

    /*
     * The Blade view used when an action has no ->pdfView(). It lists the record's visible attributes.
     */
    'default_view' => 'filament-record-pdf::default',

    /*
     * Where SavePdfAction writes files, and where queued bulk ZIPs are built.
     * Both directories are relative to the disk root.
     */
    'disk' => env('RECORD_PDF_DISK', 'local'),

    'directory' => 'record-pdfs',

    'zip_directory' => 'record-pdfs/zips',

    'visibility' => 'private',

    /*
     * Preview PDFs are kept in the cache for this many minutes, behind a signed URL
     * that only the user who opened the preview can use.
     */
    'preview_ttl' => 10,

    'cache_store' => env('RECORD_PDF_CACHE_STORE'),

    'route_prefix' => 'filament-record-pdf',

    'route_middleware' => ['web'],

    'bulk' => [
        /*
         * Bulk downloads of more records than this are built on the queue, and the user gets a
         * database notification with a download link. It needs ->databaseNotifications() on the
         * panel; without it, the ZIP is always built straight away. Set to null to never queue.
         */
        'queue_after' => 25,

        'connection' => null,

        'queue' => null,

        /*
         * Hours a queued ZIP's download link stays valid.
         */
        'link_hours' => 24,

        /*
         * The most records one bulk download may include.
         */
        'max_records' => 500,
    ],

    'mail' => [
        /*
         * Queue "Email PDF" messages instead of sending them during the request.
         */
        'queue' => false,
    ],

];
