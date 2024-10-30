<?php
/**
 * This template is used to display comments.
 */

if (post_password_required() || !comments_open()) {
    return;
}

$BVKComments = new BVKComments_Plugin();
?>

<div id="comments" class="comments-area bvk-comments-widget-area">
    <?php if ($BVKComments->isOverride()): ?>
        <?php
        $headerStyles = null;

        if (($marginBottom = $BVKComments->getWidgetMarginBottom()) !== null) {
            $headerStyles = 'margin-bottom: ' . $marginBottom . 'px;';
        }

        if (($marginTop = $BVKComments->getWidgetMarginTop()) !== null) {
            $headerStyles .= 'margin-top: ' . $marginTop . 'px;';
        }

        if (($headerType = $BVKComments->getHeaderType()) !== null): ?>
            <?= "<$headerType style=\"$headerStyles\" class=\"comments-title\">" . __('Leave A Reply', 'bvk-comments') . "</$headerType>" ?>
        <?php else: ?>
            <h2 class="comments-title"><?= __('Leave A Reply', 'bvk-comments') ?></h2>
        <?php endif; ?>
    <?php endif; ?>

    <div class="bvk-comments-widget-area__inner">
        <?php do_shortcode('[bvk_comments]'); ?>
    </div>
</div>
