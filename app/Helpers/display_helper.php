<?php

/**
 * Formatting for the generic admin list and form views.
 * Field settings are described in App\Controllers\Admin\CrudController.
 */

if (! function_exists('money')) {
    /**
     * Cents as dollars, e.g. 1050 => "$10.50".
     */
    function money($cents): string
    {
        return $cents === null || $cents === '' ? '' : '$' . number_format(((int) $cents) / 100, 2);
    }
}

if (! function_exists('field_display')) {
    /**
     * A stored value as text for a list page.
     */
    function field_display(array $field, $value): string
    {
        $type = $field['type'] ?? 'text';

        if ($type === 'checkbox') {
            return $value ? 'Yes' : 'No';
        }
        if ($value === null || $value === '') {
            return '';
        }
        if ($type === 'money') {
            return money($value);
        }
        if ($type === 'select') {
            return (string) ($field['options'][$value] ?? $value);
        }

        return (string) $value;
    }
}

if (! function_exists('field_cell')) {
    /**
     * A stored value as safe HTML for a list page cell. Email addresses become
     * mailto links; everything else is plain escaped text.
     */
    function field_cell(array $field, $value): string
    {
        if (($field['type'] ?? '') === 'email' && $value !== null && $value !== '') {
            return '<a class="cell-email" href="mailto:' . esc((string) $value, 'attr') . '" title="' . esc((string) $value, 'attr') . '">'
                . esc((string) $value) . '</a>';
        }

        return esc(field_display($field, $value));
    }
}

if (! function_exists('field_input')) {
    /**
     * The HTML input for one field on a form page.
     */
    function field_input(string $name, array $field, $value): string
    {
        $type = $field['type'] ?? 'text';
        $id   = 'f_' . $name;
        $req  = ! empty($field['required']) ? ' required' : '';

        switch ($type) {
            case 'textarea':
                return '<textarea id="' . $id . '" name="' . esc($name, 'attr') . '" rows="3"' . $req . '>' . esc((string) $value) . '</textarea>';

            case 'checkbox':
                return '<input type="hidden" name="' . esc($name, 'attr') . '" value="0">'
                    . '<input type="checkbox" id="' . $id . '" name="' . esc($name, 'attr') . '" value="1"' . ($value ? ' checked' : '') . '>';

            case 'select':
                $html = '<select id="' . $id . '" name="' . esc($name, 'attr') . '"' . $req . '><option value="">—</option>';
                foreach ($field['options'] as $key => $label) {
                    $selected = (string) $key === (string) $value ? ' selected' : '';
                    $html .= '<option value="' . esc((string) $key, 'attr') . '"' . $selected . '>' . esc($label) . '</option>';
                }

                return $html . '</select>';

            case 'money':
                $dollars = $value === null || $value === '' ? '' : (is_numeric($value) ? number_format(((int) $value) / 100, 2, '.', '') : $value);

                return '<input type="number" id="' . $id . '" name="' . esc($name, 'attr') . '" step="0.01" min="0" inputmode="decimal" value="' . esc((string) $dollars, 'attr') . '"' . $req . '>';

            case 'password':
                return '<input type="password" id="' . $id . '" name="' . esc($name, 'attr') . '" autocomplete="new-password">';

            case 'decimal':
                return '<input type="number" id="' . $id . '" name="' . esc($name, 'attr') . '" step="0.1" inputmode="decimal" value="' . esc((string) $value, 'attr') . '"' . $req . '>';

            case 'number':
                return '<input type="number" id="' . $id . '" name="' . esc($name, 'attr') . '" inputmode="numeric" value="' . esc((string) $value, 'attr') . '"' . $req . '>';

            default: // text, email, date, time
                return '<input type="' . esc($type, 'attr') . '" id="' . $id . '" name="' . esc($name, 'attr') . '" value="' . esc((string) $value, 'attr') . '"' . $req . '>';
        }
    }
}
