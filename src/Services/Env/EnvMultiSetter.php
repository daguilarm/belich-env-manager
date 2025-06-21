<?php

namespace Daguilarm\EnvManager\Services\Env;

use Daguilarm\EnvManager\Services\EnvManager;
use LogicException;

/**
 * Provides a fluent interface for setting multiple environment variables and their comments in a batch.
 */
class EnvMultiSetter
{
    protected EnvEditor $editor;

    protected EnvManager $envManager;

    /** @var array<int, array{key: string, value: string, inlineComment: string|null, commentsAbove: array<string>|null}> */
    protected array $operations = [];

    protected ?string $activeKey = null;

    protected ?string $activeValue = null;

    protected ?string $activeInlineComment = null;

    protected ?array $activeCommentsAbove = null;

    public function __construct(EnvEditor $editor, EnvManager $envManager)
    {
        $this->editor = $editor;
        $this->envManager = $envManager;
    }

    /**
     * Sets the key and value for the current variable being configured in the batch.
     * If a previous variable was being configured, it's finalized and added to the batch.
     */
    public function setItem(string $key, string $value): self
    {
        $this->finalizeCurrentItem();

        $this->activeKey = $key;
        $this->activeValue = $value;
        $this->activeInlineComment = null;
        $this->activeCommentsAbove = null;

        return $this;
    }

    /**
     * Adds an inline comment to the current variable being configured.
     */
    public function commentLine(?string $commentText): self
    {
        if ($this->activeKey === null) {
            throw new LogicException('setItem() must be called before adding an inline comment.');
        }
        $this->activeInlineComment = $commentText;

        return $this;
    }

    /**
     * Adds block comments above the current variable being configured.
     */
    public function commentsAbove(?array $commentsArray): self
    {
        if ($this->activeKey === null) {
            throw new LogicException('setItem() must be called before adding comments above.');
        }
        $this->activeCommentsAbove = $commentsArray;

        return $this;
    }

    /**
     * Finalizes the current variable being configured and adds it to the batch.
     */
    private function finalizeCurrentItem(): void
    {
        if ($this->activeKey !== null) {
            $this->operations[] = [
                'key' => $this->activeKey,
                'value' => $this->activeValue,
                'inlineComment' => $this->activeInlineComment,
                'commentsAbove' => $this->activeCommentsAbove,
            ];
        }
    }

    /**
     * Saves all changes made to the .env file via the EnvManager.
     */
    public function save(): bool
    {
        $this->finalizeCurrentItem(); // Ensure the last item is added

        foreach ($this->operations as $operation) {
            $this->editor->set($operation['key'], $operation['value'], $operation['inlineComment'], $operation['commentsAbove']);
        }

        // Reset state for potential reuse of this EnvMultiSetter instance (though typically it's one-shot)
        $this->operations = [];
        $this->activeKey = null;
        $this->activeValue = null;
        $this->activeInlineComment = null;
        $this->activeCommentsAbove = null;

        return $this->envManager->save();
    }
}
