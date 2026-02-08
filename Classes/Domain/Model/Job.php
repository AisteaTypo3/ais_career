<?php

declare(strict_types=1);

namespace Aistea\AisCareer\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Domain\Model\Category;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;

class Job extends AbstractEntity
{
    protected string $title = '';
    protected string $reference = '';
    protected string $slug = '';
    protected string $description = '';
    protected string $responsibilities = '';
    protected string $qualifications = '';
    protected string $benefits = '';
    protected string $country = '';
    protected string $city = '';
    protected string $locationLabel = '';
    protected string $department = '';
    protected string $contractType = '';
    protected bool $remotePossible = false;
    protected ?\DateTime $employmentStart = null;
    protected ?\DateTime $publishedFrom = null;
    protected ?\DateTime $publishedTo = null;
    protected bool $isActive = true;
    protected int $sorting = 0;
    protected ObjectStorage $categories;
    protected ObjectStorage $attachments;
    protected string $contactEmail = '';

    public function __construct()
    {
        $this->categories = new ObjectStorage();
        $this->attachments = new ObjectStorage();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): void
    {
        $this->reference = $reference;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getResponsibilities(): string
    {
        return $this->responsibilities;
    }

    public function setResponsibilities(string $responsibilities): void
    {
        $this->responsibilities = $responsibilities;
    }

    public function getQualifications(): string
    {
        return $this->qualifications;
    }

    public function setQualifications(string $qualifications): void
    {
        $this->qualifications = $qualifications;
    }

    public function getBenefits(): string
    {
        return $this->benefits;
    }

    public function setBenefits(string $benefits): void
    {
        $this->benefits = $benefits;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    public function getCountryLabel(): string
    {
        $code = strtoupper(trim($this->country));
        if ($code === '') {
            return '';
        }
        if (!class_exists(\Locale::class)) {
            return $code;
        }
        $label = \Locale::getDisplayRegion('-' . $code, \Locale::getDefault());
        return $label !== '' ? $label : $code;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    public function getLocationLabel(): string
    {
        return $this->locationLabel;
    }

    public function setLocationLabel(string $locationLabel): void
    {
        $this->locationLabel = $locationLabel;
    }

    public function getDepartment(): string
    {
        return $this->department;
    }

    public function setDepartment(string $department): void
    {
        $this->department = $department;
    }

    public function getContractType(): string
    {
        return $this->contractType;
    }

    public function setContractType(string $contractType): void
    {
        $this->contractType = $contractType;
    }

    public function isRemotePossible(): bool
    {
        return $this->remotePossible;
    }

    public function setRemotePossible(bool $remotePossible): void
    {
        $this->remotePossible = $remotePossible;
    }

    public function getEmploymentStart(): ?\DateTime
    {
        return $this->employmentStart;
    }

    public function setEmploymentStart(?\DateTime $employmentStart): void
    {
        $this->employmentStart = $employmentStart;
    }

    public function getPublishedFrom(): ?\DateTime
    {
        return $this->publishedFrom;
    }

    public function setPublishedFrom(?\DateTime $publishedFrom): void
    {
        $this->publishedFrom = $publishedFrom;
    }

    public function getPublishedTo(): ?\DateTime
    {
        return $this->publishedTo;
    }

    public function setPublishedTo(?\DateTime $publishedTo): void
    {
        $this->publishedTo = $publishedTo;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getSorting(): int
    {
        return $this->sorting;
    }

    public function setSorting(int $sorting): void
    {
        $this->sorting = $sorting;
    }

    /**
     * @return ObjectStorage<Category>
     */
    public function getCategories(): ObjectStorage
    {
        return $this->categories;
    }

    /**
     * @param ObjectStorage<Category> $categories
     */
    public function setCategories(ObjectStorage $categories): void
    {
        $this->categories = $categories;
    }

    public function addCategory(Category $category): void
    {
        $this->categories->attach($category);
    }

    public function removeCategory(Category $category): void
    {
        $this->categories->detach($category);
    }

    /**
     * @return ObjectStorage<FileReference>
     */
    public function getAttachments(): ObjectStorage
    {
        return $this->attachments;
    }

    /**
     * @param ObjectStorage<FileReference> $attachments
     */
    public function setAttachments(ObjectStorage $attachments): void
    {
        $this->attachments = $attachments;
    }

    public function addAttachment(FileReference $file): void
    {
        $this->attachments->attach($file);
    }

    public function removeAttachment(FileReference $file): void
    {
        $this->attachments->detach($file);
    }

    public function getContactEmail(): string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(string $contactEmail): void
    {
        $this->contactEmail = $contactEmail;
    }
}
