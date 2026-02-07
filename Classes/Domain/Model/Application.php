<?php

declare(strict_types=1);

namespace Aistea\AisCareer\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;

class Application extends AbstractEntity
{
    protected ?Job $job = null;
    protected string $firstName = '';
    protected string $lastName = '';
    protected string $email = '';
    protected string $phone = '';
    protected string $message = '';
    protected ?FileReference $cvFile = null;
    protected bool $consentPrivacy = false;
    protected ?\DateTime $createdAt = null;

    public function getJob(): ?Job
    {
        return $this->job;
    }

    public function setJob(?Job $job): void
    {
        $this->job = $job;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): void
    {
        $this->phone = $phone;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function getCvFile(): ?FileReference
    {
        return $this->cvFile;
    }

    public function setCvFile(?FileReference $cvFile): void
    {
        $this->cvFile = $cvFile;
    }

    public function isConsentPrivacy(): bool
    {
        return $this->consentPrivacy;
    }

    public function setConsentPrivacy(bool $consentPrivacy): void
    {
        $this->consentPrivacy = $consentPrivacy;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
