<?php if (($pagination['last_page'] ?? 1) > 1): ?>
    <?php
    $query = $_GET;
    unset($query['page']);
    $baseQuery = http_build_query($query);
    ?>
    <nav class="pagination" aria-label="გვერდები">
        <?php for ($pageNumber = 1; $pageNumber <= $pagination['last_page']; $pageNumber++): ?>
            <a class="<?= $pageNumber === $pagination['current_page'] ? 'active' : '' ?>"
               href="?<?= e($baseQuery . ($baseQuery ? '&' : '') . 'page=' . $pageNumber) ?>">
                <?= $pageNumber ?>
            </a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

