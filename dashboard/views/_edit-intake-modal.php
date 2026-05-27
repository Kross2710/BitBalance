<?php
/**
 * Shared "Edit Intake Entry" modal markup.
 *
 * Include once on any page that wants to edit intake rows. The page is responsible
 * for wiring its own open/close/submit handlers — see _intake-row-js.php for the
 * shared helpers that fill the form and patch the row after a successful save.
 *
 * Optional:
 *   $modalTitle — heading text. Defaults to "Edit Entry".
 */
$_modalTitle = $modalTitle ?? 'Edit Entry';
?>
<div id="editIntakeModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" id="closeEditModal">&times;</span>
        <h3><?= htmlspecialchars($_modalTitle) ?></h3>
        <form id="editIntakeForm">
            <input type="hidden" id="edit_intake_id" name="intake_id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_food_item">Food Name</label>
                    <input type="text" id="edit_food_item" name="food_item" required>
                </div>
                <div class="form-group">
                    <label for="edit_calories">Calories</label>
                    <input type="number" id="edit_calories" name="calories" required>
                </div>
                <div class="form-group macros-input-group">
                    <label class="macros-input-label">Macros <small>(grams, optional)</small></label>
                    <div class="macros-input-row">
                        <div class="macro-input p">
                            <label for="edit_protein">P</label>
                            <input type="number" id="edit_protein" name="protein" min="0" max="999" step="0.1" placeholder="0">
                        </div>
                        <div class="macro-input c">
                            <label for="edit_carbs">C</label>
                            <input type="number" id="edit_carbs" name="carbs" min="0" max="999" step="0.1" placeholder="0">
                        </div>
                        <div class="macro-input f">
                            <label for="edit_fat">F</label>
                            <input type="number" id="edit_fat" name="fat" min="0" max="999" step="0.1" placeholder="0">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_meal_category">Category</label>
                    <select id="edit_meal_category" name="meal_category" required>
                        <option value="" disabled>Select Category</option>
                        <option value="breakfast">Breakfast</option>
                        <option value="lunch">Lunch</option>
                        <option value="dinner">Dinner</option>
                        <option value="snack">Snack</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" id="cancelEditBtn">Cancel</button>
                <button type="submit" class="btn-submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>
