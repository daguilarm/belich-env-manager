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
     *
     * @param EnvEditor $editor The EnvEditor instance.
     * @param EnvManager $envManager The EnvManager instance to delegate save operations.
     * @param string $key The variable key.
     * @param string $value The variable value.
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
     *
     * @param string|null $commentText The inline comment. Pass an empty string to clear, or null to make no change from default.
     * @return $this
     */
    public function commentLine(?string $commentText): self
    {
        $this->editor->set($this->key, $this->value, $commentText, null);
        return $this;
    }

    /**
     * Sets or updates the block comments above the variable.
     *
     * @param array<string>|null $commentsArray The array of comment lines. Pass an empty array to clear, or null to make no change from default.
     * @return $this
     */
    public function commentsAbove(?array $commentsArray): self
    {
        $this->editor->set($this->key, $this->value, null, $commentsArray);
        return $this;
    }

    /**
     * Saves all changes made to the .env file via the EnvManager.
     *
     * @return bool True on success, false on failure.
     */
    public function save(): bool
    {
        return $this->envManager->save();
    }
}