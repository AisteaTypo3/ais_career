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
    protected ?FileReference $portfolioFile = null;
    protected ?FileReference $additionalFile = null;
    protected bool $consentPrivacy = false;
    protected ?\DateTime $createdAt = null;
    protected string $doubleOptInToken = '';
    protected ?\DateTime $doubleOptInConfirmedAt = null;

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

    public function getPortfolioFile(): ?FileReference
    {
        return $this->portfolioFile;
    }

    public function setPortfolioFile(?FileReference $portfolioFile): void
    {
        $this->portfolioFile = $portfolioFile;
    }

    public function getAdditionalFile(): ?FileReference
    {
        return $this->additionalFile;
    }

    public function setAdditionalFile(?FileReference $additionalFile): void
    {
        $this->additionalFile = $additionalFile;
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

    public function getDoubleOptInToken(): string
    {
        return $this->doubleOptInToken;
    }

    public function setDoubleOptInToken(string $doubleOptInToken): void
    {
        $this->doubleOptInToken = $doubleOptInToken;
    }

    public function getDoubleOptInConfirmedAt(): ?\DateTime
    {
        return $this->doubleOptInConfirmedAt;
    }

    public function setDoubleOptInConfirmedAt(?\DateTime $doubleOptInConfirmedAt): void
    {
        $this->doubleOptInConfirmedAt = $doubleOptInConfirmedAt;
    }
}
