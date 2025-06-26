<?php

namespace Condoedge\Finance\Models\Dto\Gl;

use Condoedge\Finance\Enums\SegmentDefaultHandlerEnum;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Services\AccountSegmentValidator;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

/**
 * Create or Update Segment Structure DTO
 * 
 * Used to define or update segment structure definitions.
 * Segments define the position and length of account code components.
 * 
 * @property int|null $id Segment ID for updates (null for create)
 * @property string $segment_description Description of what this segment represents
 * @property int $segment_position Position in the account code (1, 2, 3, etc.)
 * @property int $segment_length Maximum length of values for this segment
 * @property bool $is_active Whether this segment is active
 * @property string|null $default_handler Default handler type for automatic value generation
 * @property array|null $default_handler_config Configuration for the default handler
 */
class CreateOrUpdateSegmentDto extends ValidatedDTO
{
    public ?int $id;
    public string $segment_description;
    public int $segment_position;
    public int $segment_length;
    public ?string $default_handler;
    public ?array $default_handler_config;

    public function rules(): array
    {
        $validHandlers = collect(SegmentDefaultHandlerEnum::cases())
            ->pluck('value')
            ->implode(',');
            
        return [
            'id' => 'nullable|integer|exists:fin_account_segments,id',
            'segment_description' => 'required|string|max:255',
            'segment_position' => 'required|integer|min:1',
            'segment_length' => 'required|integer|min:1|max:10',
            'default_handler' => 'nullable|string|in:' . $validHandlers,
            'default_handler_config' => 'nullable|array',
        ];
    }
    
    public function casts(): array
    {
        return [
            'id' => new IntegerCast(),
            'segment_description' => new StringCast(),
            'segment_position' => new IntegerCast(),
            'segment_length' => new IntegerCast(),
            'is_active' => new BooleanCast(),
            'default_handler' => new StringCast(),
            'default_handler_config' => new ArrayCast(),
        ];
    }
    
    public function defaults(): array
    {
        return [
            'default_handler' => null,
            'default_handler_config' => null,
        ];
    }

    public function after(\Illuminate\Validation\Validator $validator): void
    {
        $segmentPosition = $this->dtoData['segment_position'] ?? null;
        $id = $this->dtoData['id'] ?? null;
        $defaultHandler = $this->dtoData['default_handler'] ?? null;
        $defaultHandlerConfig = $this->dtoData['default_handler_config'] ?? null;

        $segmentsValidator = app(AccountSegmentValidator::class);
        
        $segmentsValidator->validateSegmentPosition($segmentPosition, $id);
        
        // Validate handler configuration if handler is set
        if ($defaultHandler) {
            $handler = SegmentDefaultHandlerEnum::tryFrom($defaultHandler);
            if ($handler) {
                $configErrors = $handler->validateConfig($defaultHandlerConfig);
                foreach ($configErrors as $field => $error) {
                    $validator->errors()->add("default_handler_config.{$field}", $error);
                }
            }
        }

        $this->validateLastSegmentIsAccount($validator);
    }

    protected function validateLastSegmentIsAccount(\Illuminate\Validation\Validator $validator): void
    {
        $segmentPosition = $this->dtoData['segment_position'] ?? null;
        $segmentDescription = $this->dtoData['segment_description'] ?? null;
        $maxPosition = AccountSegment::max('segment_position') ?? 0;
        
        // If this is going to be the last segment
        if ($segmentPosition >= $maxPosition || $maxPosition === 0) {
            // Check if it's account-related
            if (!str_contains(strtolower($segmentDescription), 'account')) {
                $validator->errors()->add('segment_description', __('finance.last-segment-must-contain-account-in-description'));
            }
        }

        // If editing an existing segment that is currently the last one
        if ($this->dtoData['id']) {
            $segment = AccountSegment::find($this->dtoData['id']);
            if ($segment && $segment->segment_position === $maxPosition) {
                if (!str_contains(strtolower($segmentDescription), 'account')) {
                    $validator->errors()->add('segment_description', __('finance.last-segment-must-contain-account-in-description'));
                }
            }
        }
    }
}
