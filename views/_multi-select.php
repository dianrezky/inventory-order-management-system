<?php

/** @var string $name         Field name, e.g. "category" */
/** @var string $label         Human-readable label */
/** @var array<string, string> $options    [value => label] pairs */
/** @var list<string|int> $selected        Currently selected values (array) */
/** @var string|null $id         Override ID (default: ms-{name}) */
/** @var string|null $placeholder Override placeholder */

$id         = $id         ?? ('ms-' . $name);
$placeholder = $placeholder ?? ('Select ' . strtolower($label) . '...');
$selected   = $selected   ?? [];
$options    = $options    ?? [];
?>
<div class="form-field">
    <label class="form-field__label" for="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
    </label>
    <div class="ms-wrapper" id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>-wrapper">

        <!-- Visible tag + search input -->
        <div class="ms-input" role="combobox" aria-haspopup="listbox" aria-expanded="false" aria-controls="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>-dropdown">
            <input
                type="text"
                class="ms-search"
                placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="off"
                aria-autocomplete="list"
                aria-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
                aria-controls="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>-dropdown"
            >
        </div>

        <!-- Dropdown option list -->
        <div class="ms-dropdown" id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>-dropdown" role="listbox">
            <?php if (count($options) === 0): ?>
                <div class="ms-dropdown__empty">No options available</div>
            <?php else: ?>
                <?php foreach ($options as $value => $optionLabel): ?>
                    <?php
                    $isSel = in_array((string) $value, array_map('strval', $selected), true);
                    ?>
                    <div
                        class="ms-option<?= $isSel ? ' is-selected' : '' ?>"
                        data-value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"
                        role="option"
                        aria-selected="<?= $isSel ? 'true' : 'false' ?>"
                    >
                        <span class="ms-option__check">
                            <svg viewBox="0 0 10 10" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="1.5,5 4,7.5 8.5,2.5"/>
                            </svg>
                        </span>
                        <?= htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Hidden native select for form submission -->
        <select
            class="ms-native-select"
            id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"
            name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>[]"
            multiple
            tabindex="-1"
            aria-hidden="true"
        >
            <?php foreach ($options as $value => $optionLabel): ?>
                <?php
                $isSel = in_array((string) $value, array_map('strval', $selected), true);
                ?>
                <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" <?= $isSel ? 'selected' : '' ?>>
                    <?= htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>

</div>
</div>
