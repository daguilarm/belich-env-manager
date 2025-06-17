<?php

namespace Daguilar\BelichEnvManager\Services\Env;

use Daguilar\BelichEnvManager\Services\EnvManager;

/**
 * Provides a fluent interface for setting a variable's value and its comments.
 */
class EnvVariableSetter
{
    protected EnvEditor $editor;

    protected EnvManager $envManager;

    protected string $key;

    protected string $value;

    /**
     * EnvVariableSetter constructor.
     */
    public function __construct(EnvEditor $editor, EnvManager $envManager, string $key, string $value)
    {
        $this->editor = $editor;
        $this->envManager = $envManager;
        $this->key = $key;
        $this->value = $value;

        // Set the value initially, preserving any existing comments for now.
        // `null` for comment parameters in `editor->set` means "do not change this comment type".
        $this->editor->set($this->key, $this->value, null, null);
    }

    /**
     * Sets or updates the comment on the same line as the variable (inline comment).
     */
    public function commentLine(?string $commentText): self
    {
        $this->editor->set($this->key, $this->value, $commentText, null);

        return $this;
    }

    /**
     * Sets or updates the block comments above the variable.
     */
    public function commentsAbove(?array $commentsArray): self
    {
        $this->editor->set($this->key, $this->value, null, $commentsArray);

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
