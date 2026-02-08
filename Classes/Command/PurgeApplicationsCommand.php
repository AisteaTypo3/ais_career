<?php

declare(strict_types=1);

namespace Aistea\AisCareer\Command;

use Aistea\AisCareer\Domain\Repository\ApplicationRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

class PurgeApplicationsCommand extends Command
{
    public function __construct(
        protected readonly ApplicationRepository $applicationRepository,
        protected readonly PersistenceManagerInterface $persistenceManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('aiscareer:purge-applications')
            ->setDescription('Purge applications older than the given number of days (GDPR).')
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Delete applications older than this many days', '180');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int)$input->getOption('days');
        if ($days <= 0) {
            $output->writeln('<error>Days must be a positive integer.</error>');
            return Command::FAILURE;
        }

        $cutoff = (new \DateTime())->modify('-' . $days . ' days');
        $apps = $this->applicationRepository->findOlderThan($cutoff);
        $count = 0;

        foreach ($apps as $application) {
            $this->deleteApplicationFolder($application->getUid(), $application->getFirstName(), $application->getLastName());
            $this->applicationRepository->remove($application);
            $count++;
        }

        $this->persistenceManager->persistAll();
        $output->writeln(sprintf('Purged %d applications older than %d days.', $count, $days));

        return Command::SUCCESS;
    }

    private function deleteApplicationFolder(int $uid, string $firstName, string $lastName): void
    {
        if ($uid <= 0) {
            return;
        }

        $storage = GeneralUtility::makeInstance(ResourceFactory::class)->getStorageObject(1);
        $baseIdentifier = 'user_upload/ais_career/';
        if (!$storage->hasFolder($baseIdentifier)) {
            return;
        }
        $baseFolder = $storage->getFolder($baseIdentifier);

        $slug = $this->slugifyFolderName(trim($firstName . ' ' . $lastName));
        $candidates = [
            $uid . ($slug !== '' ? '-' . $slug : ''),
            (string)$uid,
        ];

        foreach ($candidates as $folderName) {
            if ($folderName === '') {
                continue;
            }
            if ($baseFolder->hasFolder($folderName)) {
                $folder = $baseFolder->getSubfolder($folderName);
                $storage->deleteFolder($folder, true);
                return;
            }
        }
    }

    private function slugifyFolderName(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9]+/', '-', $name) ?? '';
        $name = trim($name, '-');
        if (strlen($name) > 60) {
            $name = substr($name, 0, 60);
            $name = rtrim($name, '-');
        }
        return $name;
    }
}
