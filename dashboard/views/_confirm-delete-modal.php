<?php
/**
 * Shared "Confirm Delete" modal markup.
 * Can be included once on any dashboard page to provide custom-styled delete confirmation.
 */
?>
<div id="confirmDeleteModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" id="closeConfirmDeleteModal" aria-label="<?= t('common.close') ?>">&times;</span>
        <h3>
            <i class="fas fa-exclamation-triangle" style="color: var(--color-danger); margin-right: 8px;"></i>
            <?= t('intake.delete.confirm_title') ?>
        </h3>
        <div class="modal-body">
            <p class="modal-desc" id="confirmDeleteDesc">
                <?= t('intake.delete.confirm_desc') ?>
            </p>
        </div>
        <div class="modal-footer" style="justify-content: center; gap: 16px;">
            <button type="button" class="btn-cancel" id="cancelDeleteBtn"><?= t('common.cancel') ?></button>
            <button type="button" class="btn-danger" id="confirmDeleteBtn"><?= t('common.delete') ?></button>
        </div>
    </div>
</div>
