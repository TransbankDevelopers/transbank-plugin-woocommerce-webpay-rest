<?php
namespace Transbank\Plugin\Helpers;

use Monolog\LogRecord;

class LoggerMaskProcessor
{
    private MaskData $masker;

    public function __construct(MaskData $masker)
    {
        $this->masker = $masker;
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;

        if (!empty($context)) {
            try {
                $masked = $this->masker->maskData($context);
                return $record->with(context: $masked);
            } catch (\Throwable $e) {
                return $record->with(
                    context: array_merge($context, [
                        'mask_error' => $e->getMessage(),
                    ])
                );
            }
        }

        return $record;
    }
}
