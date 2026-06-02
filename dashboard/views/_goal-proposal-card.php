<?php
/**
 * PT goal-proposal card (Phase 1).
 *
 * Shown on Overview when a still-linked trainer has proposed a new calorie goal
 * the client hasn't responded to yet. The client accepts (writes the new goal to
 * userGoal) or keeps their current goal. Consent stays with the client.
 *
 * Expects: $goalProposal (row from pt_goal_proposal + trainer name fields),
 *          $userGoal (current calorie goal, may be empty).
 */
$gpTrainer  = trim(($goalProposal['first_name'] ?? '') . ' ' . ($goalProposal['last_name'] ?? ''));
if ($gpTrainer === '') {
    $gpTrainer = (string) ($goalProposal['user_name'] ?? '');
}
$gpNew      = (int) $goalProposal['calorie_goal'];
$gpCurrent  = !empty($userGoal) ? (int) $userGoal : 0;
$gpNote     = trim((string) ($goalProposal['note'] ?? ''));
$gpHasMacros = (($goalProposal['protein_goal'] ?? null) !== null
    && ($goalProposal['carbs_goal'] ?? null) !== null
    && ($goalProposal['fat_goal'] ?? null) !== null);
?>
<style>
    .goal-proposal-card {
        display: flex; align-items: center; flex-wrap: wrap; gap: 16px;
        padding: 18px 20px; margin-bottom: 20px;
        border: 2px solid var(--color-secondary);
        border-radius: var(--radius-lg, 16px);
        background: var(--color-surface);
        box-shadow: 0 8px 0 var(--color-border-subtle), var(--shadow-sm);
    }
    .goal-proposal-card__icon {
        width: 44px; height: 44px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        border-radius: 50%;
        background: var(--color-surface-alt);
        color: var(--color-secondary);
        border: 2px solid var(--color-secondary);
        font-size: 18px;
    }
    .goal-proposal-card__body { flex: 1; min-width: 220px; }
    .goal-proposal-card__title { margin: 0 0 4px; font-size: 14px; font-weight: 700; color: var(--color-secondary); }
    .goal-proposal-card__nums { font-size: 14px; color: var(--color-text); }
    .goal-proposal-card__nums strong { font-size: 18px; }
    .goal-proposal-card__old { color: var(--color-text-secondary); text-decoration: line-through; }
    .goal-proposal-card__macros { margin-top: 4px; font-size: 13px; font-weight: 600; color: var(--color-text-secondary); }
    .goal-proposal-card__note { margin: 6px 0 0; font-size: 13px; font-style: italic; color: var(--color-text); }
    .goal-proposal-card__actions { display: flex; gap: 8px; }
    .goal-proposal-card__btn {
        border: none; cursor: pointer; font-weight: 700;
        padding: 10px 18px; border-radius: var(--radius-md, 10px);
    }
    .goal-proposal-card__btn--accept {
        background: var(--color-secondary); color: #fff;
        box-shadow: 0 4px 0 var(--color-secondary-hover, rgba(0,0,0,.2));
    }
    .goal-proposal-card__btn--keep {
        background: var(--color-surface); color: var(--color-text);
        border: 2px solid var(--color-border);
    }
    .goal-proposal-card__btn[disabled] { opacity: .6; cursor: default; }
</style>
<div class="goal-proposal-card" id="goalProposalCard" data-id="<?= (int) $goalProposal['id'] ?>">
    <div class="goal-proposal-card__icon"><i class="fas fa-bullseye" aria-hidden="true"></i></div>
    <div class="goal-proposal-card__body">
        <h4 class="goal-proposal-card__title">
            <?= t('goalproposal.title', ['name' => htmlspecialchars($gpTrainer)]) ?>
        </h4>
        <div class="goal-proposal-card__nums">
            <strong><?= number_format($gpNew) ?></strong> <?= t('common.kcal') ?>
            <?php if ($gpCurrent > 0): ?>
                <span class="goal-proposal-card__old">(<?= t('goalproposal.current') ?>: <?= number_format($gpCurrent) ?>)</span>
            <?php endif; ?>
        </div>
        <?php if ($gpHasMacros): ?>
            <div class="goal-proposal-card__macros">
                P <?= (int) $goalProposal['protein_goal'] ?>g &middot;
                C <?= (int) $goalProposal['carbs_goal'] ?>g &middot;
                F <?= (int) $goalProposal['fat_goal'] ?>g
            </div>
        <?php endif; ?>
        <?php if ($gpNote !== ''): ?>
            <p class="goal-proposal-card__note">"<?= htmlspecialchars($gpNote) ?>"</p>
        <?php endif; ?>
    </div>
    <div class="goal-proposal-card__actions">
        <button type="button" class="goal-proposal-card__btn goal-proposal-card__btn--keep" data-decision="decline">
            <?= t('goalproposal.keep') ?>
        </button>
        <button type="button" class="goal-proposal-card__btn goal-proposal-card__btn--accept" data-decision="accept">
            <i class="fas fa-check" aria-hidden="true"></i> <?= t('goalproposal.accept') ?>
        </button>
    </div>
</div>
<script>
    (function () {
        'use strict';
        const card = document.getElementById('goalProposalCard');
        if (!card) return;
        const CSRF = <?= json_encode(csrf_token()) ?>;
        const ENDPOINT = '<?= BASE_URL ?>dashboard/handlers/respond_goal_proposal.php';

        card.querySelectorAll('.goal-proposal-card__btn').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const decision = btn.dataset.decision;
                card.querySelectorAll('.goal-proposal-card__btn').forEach(function (b) { b.disabled = true; });

                try {
                    const fd = new FormData();
                    fd.append('proposal_id', card.dataset.id);
                    fd.append('decision', decision);
                    fd.append('csrf_token', CSRF);
                    const res = await fetch(ENDPOINT, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'fetch' },
                        body: fd
                    });
                    const data = await res.json();
                    if (!data.ok) {
                        alert(data.error || 'Action failed');
                        card.querySelectorAll('.goal-proposal-card__btn').forEach(function (b) { b.disabled = false; });
                        return;
                    }
                    if (decision === 'accept') {
                        // Reload so the calorie ring, status and macro widget pick up the new goal.
                        window.location.reload();
                    } else {
                        card.remove();
                    }
                } catch (err) {
                    console.error('[goal-proposal] failed:', err);
                    alert('Connection error');
                    card.querySelectorAll('.goal-proposal-card__btn').forEach(function (b) { b.disabled = false; });
                }
            });
        });
    })();
</script>
