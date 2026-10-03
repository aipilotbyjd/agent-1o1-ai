<?php

namespace App\Services\Assistant\Inbox;

use Carbon\CarbonInterface;

/**
 * Everything Smart Inbox does to a mailbox. Gmail now; Outlook implements
 * the same contract later, so classifying and drafting stay provider-free.
 */
interface Mailbox
{
    public function ownAddress(): string;

    /**
     * @return list<string> ids of inbox messages received after `$since`
     */
    public function newMessageIds(CarbonInterface $since, int $limit): array;

    public function message(string $id): MailMessage;

    /**
     * @return list<array{id: string, name: string, user: bool}>
     */
    public function labels(): array;

    public function createLabel(string $name): string;

    public function renameLabel(string $id, string $name): void;

    /**
     * @param  list<string>  $add
     * @param  list<string>  $remove
     */
    public function modify(string $messageId, array $add, array $remove): void;

    public function hasSentTo(string $address): bool;

    /**
     * @return list<string> bodies of the owner's latest emails to `$address`
     */
    public function recentRepliesTo(string $address, int $limit): array;

    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     */
    public function saveDraft(?string $draftId, MailMessage $original, array $to, array $cc, string $body): string;

    /**
     * The draft's current text, or null if the owner sent or deleted it.
     */
    public function draftBody(string $draftId): ?string;
}
