<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\Domain\Model;

use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Contact extends AbstractEntity
{
    protected string $salutation = '';

    protected string $firstName = '';

    protected string $lastName = '';

    protected string $jobPosition = '';

    protected string $departmentText = '';

    protected string $room = '';

    protected string $phone = '';

    protected string $mobile = '';

    protected string $email = '';

    protected string $website = '';

    protected string $description = '';

    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $photo;

    public function __construct()
    {
        $this->photo = new ObjectStorage();
    }

    public function getSalutation(): string
    {
        return $this->salutation;
    }

    public function setSalutation(string $salutation): void
    {
        $this->salutation = $salutation;
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

    public function getJobPosition(): string
    {
        return $this->jobPosition;
    }

    public function setJobPosition(string $jobPosition): void
    {
        $this->jobPosition = $jobPosition;
    }

    public function getDepartmentText(): string
    {
        return $this->departmentText;
    }

    public function setDepartmentText(string $departmentText): void
    {
        $this->departmentText = $departmentText;
    }

    public function getRoom(): string
    {
        return $this->room;
    }

    public function setRoom(string $room): void
    {
        $this->room = $room;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): void
    {
        $this->phone = $phone;
    }

    public function getMobile(): string
    {
        return $this->mobile;
    }

    public function setMobile(string $mobile): void
    {
        $this->mobile = $mobile;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getWebsite(): string
    {
        return $this->website;
    }

    public function setWebsite(string $website): void
    {
        $this->website = $website;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return ObjectStorage<FileReference>
     */
    public function getPhoto(): ObjectStorage
    {
        return $this->photo;
    }

    public function addPhoto(FileReference $photo): void
    {
        $this->photo->attach($photo);
    }

    public function removePhoto(FileReference $photo): void
    {
        $this->photo->detach($photo);
    }
}
