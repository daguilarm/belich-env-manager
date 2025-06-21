<?php

namespace Daguilarm\EnvManager\Services\Env;

use Daguilarm\EnvManager\Services\EnvManager;

/**
 * Provides a fluent interface for setting a variable's value and its comments.
 */
class EnvVariableSetter
{
    protected EnvEditor $editor;

    protected EnvManager $envManager;

    protected string $key;

    protected string $value;

    protected ?string $currentInlineComment = null;

    protected ?array $currentCommentsAbove = null;

    /**
     * EnvVariableSetter constructor.
     */
    public function __construct(EnvEditor $editor, EnvManager $envManager, string $key, string $value)
    {
        $this->editor = $editor;
        $this->envManager = $envManager;
        $this->key = $key;
        $this->value = $value;

        // Initialize comment states
        // The initial call to editor->set will use these nulls,
        // effectively preserving existing comments if any, or setting none if new.
        // Set the value initially, preserving any existing comments for now.
        $this->editor->set($this->key, $this->value, null, null);
    }

    /**
     * Sets or updates the comment on the same line as the variable (inline comment).
     */
    public function commentLine(?string $commentText): self
    {
        $this->currentInlineComment = $commentText;
        $this->editor->set($this->key, $this->value, $this->currentInlineComment, $this->currentCommentsAbove);

        return $this;
    }

    /**
     * Sets or updates the block comments above the variable.
     */
    public function commentsAbove(?array $commentsArray): self
    {
        $this->currentCommentsAbove = $commentsArray;
        $this->editor->set($this->key, $this->value, $this->currentInlineComment, $this->currentCommentsAbove);

        return $this;
    }

    /**
     * Saves all changes made to the .env file via the EnvManager.
     */
    public function save(): bool
    {
        return $this->envManager->save();
    }
}
