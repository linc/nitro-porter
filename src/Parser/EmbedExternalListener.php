<?php

namespace Porter\Parser;

use nadar\quill\BlockListener;

/**
 * Add convenience logic for "embed-external" block listeners.
 */
abstract class EmbedExternalListener extends BlockListener
{
    /**
     * Detect a type of embed-external block.
     *
     * @param array $jsonInsert See insertJsonKey().
     * @param string $type One of 'link', 'quote', or 'image'.
     * @return bool Whether to process the block.
     */
    public function isEmbedExternal(array $jsonInsert, string $type): bool
    {
        // Verify basic info structure.
        if (!isset($jsonInsert['data'])) {
            return false;
        }

        // Check data.type
        if (isset($jsonInsert['data']['type']) && $jsonInsert['data']['type'] === $type) {
            return true;
        }

        // Check data.embedType
        if (isset($jsonInsert['data']['embedType']) && $jsonInsert['data']['embedType'] === $type) {
            return true;
        }

        return false;
    }
}
