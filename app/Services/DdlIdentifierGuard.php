<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

class DdlIdentifierGuard
{
    /** @var list<string> */
    private const SQL_RESERVED_KEYWORDS = [
        'add', 'all', 'alter', 'and', 'as', 'asc', 'between', 'by', 'case', 'check', 'column',
        'constraint', 'create', 'database', 'default', 'delete', 'desc', 'distinct', 'drop',
        'else', 'exists', 'foreign', 'from', 'group', 'having', 'index', 'inner', 'insert',
        'into', 'join', 'key', 'left', 'like', 'limit', 'not', 'null', 'on', 'or', 'order',
        'outer', 'primary', 'references', 'rename', 'right', 'select', 'set', 'table', 'then',
        'truncate', 'union', 'unique', 'update', 'values', 'view', 'when', 'where',
    ];

    public function assertSafeIdentifier(string $value, string $context = 'identifier'): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException(__('ddl_guard_empty_identifier'));
        }

        if (preg_match('/[^a-zA-Z0-9_]/', $trimmed) === 1) {
            throw new InvalidArgumentException(__('ddl_guard_invalid_chars'));
        }

        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $trimmed) !== 1) {
            throw new InvalidArgumentException(__('ddl_guard_invalid_format'));
        }

        if (in_array(strtolower($trimmed), self::SQL_RESERVED_KEYWORDS, true)) {
            throw new InvalidArgumentException(__('ddl_guard_reserved_keyword'));
        }

        return strtolower($trimmed);
    }

    public function assertSafeSlug(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException(__('ddl_guard_empty_slug'));
        }

        if (preg_match('/[^a-zA-Z0-9_-]/', $trimmed) === 1) {
            throw new InvalidArgumentException(__('ddl_guard_invalid_slug'));
        }

        $normalized = mb_strtolower($trimmed, 'UTF-8');

        if (in_array($normalized, self::SQL_RESERVED_KEYWORDS, true)) {
            throw new InvalidArgumentException(__('ddl_guard_reserved_slug'));
        }

        return $normalized;
    }

    public function columnNameFromLabel(string $label): string
    {
        return $this->assertSafeIdentifier(custom_field_code_from_label($label), 'column');
    }
}
