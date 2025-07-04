<?php

namespace Condoedge\Finance\Enums;

/**
 * Segment Default Handler Enum
 *
 * Defines the automatic value generation strategies for account segments.
 * Each handler provides a different method for deriving segment values automatically.
 *
 * This enum follows the same pattern as other enums in the finance package
 * for consistency and to enable facade usage.
 */
enum SegmentDefaultHandlerEnum: string
{
    use \Kompo\Models\Traits\EnumKompo;

    case TEAM = 'team';
    case PARENT_TEAM = 'parent_team';
    case MANUAL = 'manual';

    /**
     * Get human-readable label for the handler
     */
    public function label(): string
    {
        return match($this) {
            self::TEAM => __('finance.uses-current-team-id'),
            self::PARENT_TEAM => __('finance.uses-parent-team-id'),
            self::MANUAL => __('finance.manual-entry-required'),
        };
    }

    /**
     * Get description of what this handler does
     */
    public function description(): string
    {
        return match($this) {
            self::TEAM => __('finance.automatically-uses-the-current-teams-id-padded-to-segment-length'),
            self::PARENT_TEAM => __('finance.automatically-uses-the-parent-teams-id-or-code'),
            self::MANUAL => __('finance.requires-manual-selection-no-automation'),
        };
    }

    /**
     * Check if this handler requires context data
     */
    public function requiresContext(): bool
    {
        return match($this) {
            self::TEAM, self::PARENT_TEAM,
            self::MANUAL => false,
        };
    }

    /**
     * Check if this handler requires configuration
     */
    public function requiresConfig(): bool
    {
        return match($this) {
            default => false,
        };
    }

    /**
     * Get required configuration fields
     */
    public function getConfigFields(): array
    {
        return match($this) {
            default => [],
        };
    }

    /**
     * Validate handler configuration
     */
    public function validateConfig(?array $config): array
    {
        $errors = [];
        $requiredFields = $this->getConfigFields();

        foreach ($requiredFields as $field => $rules) {
            if (str_contains($rules, 'required') && empty($config[$field])) {
                $errors[$field] = __('finance.field-is-required', ['field' => $field]);
            }
        }

        return $errors;
    }
}
