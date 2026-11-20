<?php
$issuerData = $invoice['issuer_snapshot'];
$clientData = $invoice['client_snapshot'];
$embedded = isset($_GET['embed']);
$currency = $invoice['currency'];
$templateKey = in_array($invoice['template_key'], ['standard', 'modern', 'compact'], true)
    ? $invoice['template_key']
    : 'standard';
$isCompact = $templateKey === 'compact';
$formatAmount = static fn ($amount): string => number_format((float) $amount, 2, '.', ' ') . ' ' . $currency;
$filename = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $invoice['invoice_number']) . '.pdf';
$documentPages = $documentPages ?? [$invoice['items']];
$pageCount = count($documentPages);
$bodyClasses = array_filter([
    $embedded ? 'embedded-document' : '',
    $autoDownload ? 'pdf-download' : '',
    'document-template-' . $templateKey,
]);
?>
<!doctype html>
<html lang="<?= e($invoice['language']) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($invoice['invoice_number']) ?></title>
    <link rel="stylesheet" href="<?= asset('css/invoice.css') ?>">
    <style nonce="<?= e(csp_nonce()) ?>">@page { size: A4 <?= $isCompact ? 'landscape' : 'portrait' ?>; margin: 0; }</style>
</head>
<body class="<?= e(implode(' ', $bodyClasses)) ?>">
<?php if (!$embedded && !$autoDownload): ?>
    <div class="document-toolbar">
        <button type="button" data-document-action="print">ბეჭდვა / PDF-ში შენახვა</button>
        <button type="button" data-document-action="close">დახურვა</button>
    </div>
    <script nonce="<?= e(csp_nonce()) ?>">
        document.querySelectorAll('[data-document-action]').forEach((button) => {
            button.addEventListener('click', () => (button.dataset.documentAction === 'print' ? window.print() : window.close()));
        });
    </script>
<?php endif; ?>

<?php if ($autoDownload): ?>
    <div class="download-screen" id="download-screen">
        <div class="download-spinner"></div>
        <strong>PDF მზადდება…</strong>
        <p>ჩამოტვირთვა რამდენიმე წამში დაიწყება.</p>
        <button type="button" id="manual-download" hidden>ხელახლა ჩამოტვირთვა</button>
    </div>
<?php endif; ?>

<main class="invoice-document" id="invoice-document">
    <?php foreach ($documentPages as $pageIndex => $pageItems): ?>
        <?php
        $pageNumber = $pageIndex + 1;
        $isFirstPage = $pageIndex === 0;
        $isLastPage = $pageNumber === $pageCount;
        $pageClasses = [
            'invoice-paper',
            'invoice-page',
            'template-' . $templateKey,
            $isFirstPage ? 'page-first' : 'page-continuation',
            $isLastPage ? 'page-final' : '',
        ];
        ?>
        <section class="<?= e(implode(' ', array_filter($pageClasses))) ?>">
            <?php if ($invoice['status'] === 'draft'): ?>
                <div class="draft-watermark"><?= e(invoice_label($labels, 'draft')) ?></div>
            <?php endif; ?>

            <?php if ($isFirstPage): ?>
                <?php require BASE_PATH . '/app/Views/invoices/document/header.php'; ?>
                <?php require BASE_PATH . '/app/Views/invoices/document/parties.php'; ?>
            <?php else: ?>
                <?php require BASE_PATH . '/app/Views/invoices/document/continuation-header.php'; ?>
            <?php endif; ?>

            <?php require BASE_PATH . '/app/Views/invoices/document/items-table.php'; ?>

            <?php if ($isLastPage): ?>
                <?php require BASE_PATH . '/app/Views/invoices/document/summary.php'; ?>
            <?php endif; ?>

            <?php require BASE_PATH . '/app/Views/invoices/document/footer.php'; ?>
        </section>
    <?php endforeach; ?>
</main>

<?php if ($autoDownload): ?>
    <script src="<?= asset('vendor/html2pdf.bundle.min.js') ?>"></script>
    <script nonce="<?= e(csp_nonce()) ?>">
        (() => {
            const documentElement = document.getElementById('invoice-document');
            const screen = document.getElementById('download-screen');
            const manual = document.getElementById('manual-download');
            const options = {
                margin: 0,
                filename: <?= json_encode($filename, JSON_UNESCAPED_UNICODE) ?>,
                image: {type: 'jpeg', quality: 0.99},
                html2canvas: {scale: 2.2, useCORS: true, logging: false, backgroundColor: '#ffffff'},
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: <?= json_encode($isCompact ? 'landscape' : 'portrait') ?>
                },
                pagebreak: {mode: ['css', 'legacy'], avoid: ['tr', '.invoice-summary']}
            };
            const download = () => {
                manual.hidden = true;
                html2pdf().set(options).from(documentElement).save()
                    .then(() => {
                        screen.querySelector('strong').textContent = 'PDF ჩამოიტვირთა';
                        screen.querySelector('p').textContent = 'შეგიძლიათ ეს ფანჯარა დახუროთ.';
                        screen.querySelector('.download-spinner').classList.add('done');
                    })
                    .catch(() => {
                        screen.querySelector('strong').textContent = 'ჩამოტვირთვა ვერ დაიწყო';
                        screen.querySelector('p').textContent = 'დააჭირეთ ღილაკს ხელახლა საცდელად.';
                        manual.hidden = false;
                    });
            };
            manual.addEventListener('click', download);
            window.addEventListener('load', () => {
                const fontsReady = document.fonts?.ready ?? Promise.resolve();
                fontsReady.then(() => setTimeout(download, 250));
            });
        })();
    </script>
<?php endif; ?>
</body>
</html>

