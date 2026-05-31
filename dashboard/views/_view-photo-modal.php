<?php
/**
 * Shared "View Photo" modal markup.
 * Displays the meal photo uploaded by clients.
 */
?>
<div id="viewPhotoModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <span class="close-modal" id="closeViewPhotoModal" aria-label="Đóng">&times;</span>
        <h3 style="margin-bottom: 16px; font-weight: 700; color: var(--color-text);">
            <i class="fas fa-camera" style="color: var(--color-primary); margin-right: 8px;"></i>
            Ảnh chụp món ăn
        </h3>
        <div class="modal-body" style="text-align: center; padding: 8px 0;">
            <img id="viewPhotoImg" src="" style="max-width: 100%; max-height: 400px; border-radius: var(--radius-md); border: 2px solid var(--color-border); box-shadow: var(--shadow-md); object-fit: contain;" alt="Meal Photo">
        </div>
    </div>
</div>
