<?php

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 *
 */
class Alias extends Command {

  /**
   * @var \Larowlan\Tl\Connector\Connector
   */
  protected $connector;

  /**
   * @var \Larowlan\Tl\Repository\Repository
   */
  protected $repository;

  /**
   *
   */
  public function __construct(Connector $connector, Repository $repository) {
    $this->connector = $connector;
    $this->repository = $repository;
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('alias')
      ->setDescription('Manages aliases')
      ->setHelp('Manages ticket aliases')
      ->addOption('delete', NULL, InputOption::VALUE_NONE, 'Delete alias(es)')
      ->addOption('list', NULL, InputOption::VALUE_NONE, 'List aliases')
      ->addOption('edit', NULL, InputOption::VALUE_NONE, 'Edit an alias')
      ->addArgument('ticket_id', InputArgument::OPTIONAL, 'Ticket ID to add an alias for')
      ->addArgument('alias', InputArgument::OPTIONAL, 'Alias to use')
      ->addUsage('tl alias')
      ->addUsage('tl alias 12345 "foobar"')
      ->addUsage('tl alias --list')
      ->addUsage('tl alias --delete')
      ->addUsage('tl alias "foobar" --delete')
      ->addUsage('tl alias --edit')
      ->addUsage('tl alias "foobar" --edit');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    if ($input->getOption('list')) {
      return $this->listAliases($output);
    }

    if ($input->getOption('delete')) {
      return $this->deleteAliases($input, $output);
    }

    if ($input->getOption('edit')) {
      return $this->editAlias($input, $output);
    }

    return $this->addAliases($input, $output);
  }

  /**
   * Validates an alias name.
   *
   * Alias names may only contain letters, digits, underscores, hyphens, and
   * fullstops. Commas and spaces are excluded because commas are used as the multiselect
   * separator in interactive choice lists.
   *
   * @param string $alias
   *   The alias name to validate.
   *
   * @return string|null
   *   NULL if valid, or an error message string if invalid.
   */
  protected function validateAliasName(string $alias): ?string {
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $alias)) {
      return 'Alias names may only contain letters, digits, underscores, hyphens, and fullstops.';
    }
    return NULL;
  }

  /**
   * Lists all aliases.
   */
  protected function listAliases(OutputInterface $output): int {
    $table = new Table($output);
    $table->setHeaders(['Alias', 'Issue number']);
    $rows = [];
    foreach ($this->repository->listAliases() as $alias) {
      $rows[] = [$alias->alias, $alias->tid];
    }
    $table->setRows($rows);
    $table->render();
    return 0;
  }

  /**
   * Adds one or more aliases.
   *
   * If both positional args are provided, adds a single alias non-interactively.
   * Otherwise, enters an interactive loop.
   */
  protected function addAliases(InputInterface $input, OutputInterface $output): int {
    $alias = $input->getArgument('alias');
    $tid = $input->getArgument('ticket_id');

    // One-shot non-interactive add (both args provided).
    if ($alias && $tid) {
      return $this->doAddAlias($tid, $alias, $input, $output) ? 0 : 1;
    }

    // Validation errors for partial args (matches old behaviour).
    if ($tid && !$alias) {
      $output->writeln('<error>Missing alias</error>');
      return 1;
    }
    if ($alias && !$tid) {
      $output->writeln('<error>Missing ticket number</error>');
      return 1;
    }

    // Interactive add loop.
    $helper = $this->getHelper('question');
    $another = TRUE;
    do {
      $tidQuestion = new Question('Ticket ID: ');
      $newTid = $helper->ask($input, $output, $tidQuestion);
      if (!$newTid) {
        $output->writeln('<error>Ticket ID cannot be empty</error>');
        continue;
      }

      $aliasQuestion = new Question('Alias: ');
      $newAlias = $helper->ask($input, $output, $aliasQuestion);
      if (!$newAlias) {
        $output->writeln('<error>Alias cannot be empty</error>');
        continue;
      }
      if ($error = $this->validateAliasName($newAlias)) {
        $output->writeln(sprintf('<error>%s</error>', $error));
        continue;
      }

      $this->doAddAlias($newTid, $newAlias, $input, $output);

      $another = $helper->ask($input, $output, new ConfirmationQuestion('Add another? (y/N): ', FALSE));
    } while ($another);

    return 0;
  }

  /**
   * Performs the actual add, handling duplicate checking.
   *
   * @return bool TRUE on success, FALSE on failure.
   */
  protected function doAddAlias($tid, string $alias, InputInterface $input, OutputInterface $output): bool {
    if ($error = $this->validateAliasName($alias)) {
      $output->writeln(sprintf('<error>%s</error>', $error));
      return FALSE;
    }
    $existing = $this->repository->findAlias($alias);
    if ($existing) {
      $helper = $this->getHelper('question');
      $confirm = $helper->ask($input, $output, new ConfirmationQuestion(
        sprintf('Alias <comment>%s</comment> already points to ticket <comment>%s</comment>. Update to <comment>%s</comment>? (y/N): ', $alias, $existing->tid, $tid),
        FALSE
      ));
      if (!$confirm) {
        $output->writeln('Skipped.');
        return TRUE;
      }
      $this->repository->updateAlias($alias, $alias, $tid);
      $output->writeln(sprintf('Updated alias <comment>%s</comment> -> <comment>%s</comment>', $alias, $tid));
      return TRUE;
    }

    if ($this->repository->addAlias($tid, $alias)) {
      $output->writeln(sprintf('Created new alias <comment>%s</comment> -> <comment>%s</comment>', $alias, $tid));
      return TRUE;
    }

    $output->writeln('<error>Unable to create alias</error>');
    return FALSE;
  }

  /**
   * Deletes aliases.
   *
   * If an alias positional arg is provided, deletes that specific alias after
   * confirmation. Otherwise, shows an interactive multi-select list.
   */
  protected function deleteAliases(InputInterface $input, OutputInterface $output): int {
    $helper = $this->getHelper('question');
    $aliasArg = $input->getArgument('alias') ?? $input->getArgument('ticket_id');

    // Single alias delete by name.
    if ($aliasArg) {
      $existing = $this->repository->findAlias($aliasArg);
      if (!$existing) {
        $output->writeln(sprintf('<error>Alias <comment>%s</comment> not found</error>', $aliasArg));
        return 1;
      }
      $confirm = $helper->ask($input, $output, new ConfirmationQuestion(
        sprintf('Delete alias <comment>%s</comment> -> <comment>%s</comment>? (y/N): ', $existing->alias, $existing->tid),
        FALSE
      ));
      if (!$confirm) {
        $output->writeln('Cancelled.');
        return 0;
      }
      $this->repository->removeAliasByName($aliasArg);
      $output->writeln(sprintf('Removed alias <comment>%s</comment>', $aliasArg));
      return 0;
    }

    // Interactive multi-select delete.
    $list = $this->repository->listAliases();
    if (empty($list)) {
      $output->writeln('No aliases found.');
      return 0;
    }

    $choices = [];
    foreach ($list as $row) {
      $choices[] = sprintf('%s -> %s', $row->alias, $row->tid);
    }

    $question = new ChoiceQuestion(
      'Select aliases to delete (comma-separated numbers, or press Enter to cancel):',
      $choices
    );
    $question->setMultiselect(TRUE);
    $question->setErrorMessage('Invalid selection: %s');

    $selected = $helper->ask($input, $output, $question);
    if (empty($selected)) {
      $output->writeln('Nothing selected.');
      return 0;
    }

    // Parse alias names back out of the "alias -> tid" strings.
    $toDelete = [];
    foreach ($selected as $choice) {
      [$aliasName] = explode(' -> ', $choice, 2);
      $toDelete[] = $aliasName;
    }

    $output->writeln('The following aliases will be deleted:');
    foreach ($selected as $choice) {
      $output->writeln('  ' . $choice);
    }
    $confirm = $helper->ask($input, $output, new ConfirmationQuestion(
      sprintf('Delete %d alias(es)? (y/N): ', count($toDelete)),
      FALSE
    ));
    if (!$confirm) {
      $output->writeln('Cancelled.');
      return 0;
    }

    $this->repository->removeNamedAliases($toDelete);
    foreach ($toDelete as $aliasName) {
      $output->writeln(sprintf('Removed alias <comment>%s</comment>', $aliasName));
    }

    return 0;
  }

  /**
   * Edits an alias.
   *
   * If an alias positional arg is provided, edits that alias directly.
   * Otherwise, shows an interactive picker first.
   */
  protected function editAlias(InputInterface $input, OutputInterface $output): int {
    $helper = $this->getHelper('question');
    $aliasArg = $input->getArgument('alias') ?? $input->getArgument('ticket_id');

    if ($aliasArg) {
      $existing = $this->repository->findAlias($aliasArg);
      if (!$existing) {
        $output->writeln(sprintf('<error>Alias <comment>%s</comment> not found</error>', $aliasArg));
        return 1;
      }
    }
    else {
      // Interactive picker.
      $list = $this->repository->listAliases();
      if (empty($list)) {
        $output->writeln('No aliases found.');
        return 0;
      }

      $choices = [];
      foreach ($list as $row) {
        $choices[] = sprintf('%s -> %s', $row->alias, $row->tid);
      }

      $question = new ChoiceQuestion('Select alias to edit:', $choices);
      $question->setErrorMessage('Invalid selection: %s');
      $selected = $helper->ask($input, $output, $question);

      [$aliasName] = explode(' -> ', $selected, 2);
      $existing = $this->repository->findAlias($aliasName);
    }

    // Prompt for new alias name and ticket ID, pre-filled with current values.
    $newAliasQuestion = new Question(sprintf('Alias [<comment>%s</comment>]: ', $existing->alias), $existing->alias);
    $newAliasQuestion->setValidator(function ($value) {
      if ($error = $this->validateAliasName((string) $value)) {
        throw new \InvalidArgumentException($error);
      }
      return $value;
    });
    $newAlias = $helper->ask($input, $output, $newAliasQuestion);

    $newTidQuestion = new Question(sprintf('Ticket ID [<comment>%s</comment>]: ', $existing->tid), (string) $existing->tid);
    $newTid = $helper->ask($input, $output, $newTidQuestion);

    if ($newAlias === $existing->alias && (string) $newTid === (string) $existing->tid) {
      $output->writeln('No changes made.');
      return 0;
    }

    // Check for collision if alias name is changing.
    if ($newAlias !== $existing->alias) {
      $collision = $this->repository->findAlias($newAlias);
      if ($collision) {
        $confirm = $helper->ask($input, $output, new ConfirmationQuestion(
          sprintf('Alias <comment>%s</comment> already points to ticket <comment>%s</comment>. Overwrite? (y/N): ', $newAlias, $collision->tid),
          FALSE
        ));
        if (!$confirm) {
          $output->writeln('Cancelled.');
          return 0;
        }
        // Remove the colliding alias first.
        $this->repository->removeAliasByName($newAlias);
      }
    }

    $this->repository->updateAlias($existing->alias, $newAlias, $newTid);
    $output->writeln(sprintf('Updated alias <comment>%s</comment> -> <comment>%s</comment>', $newAlias, $newTid));
    return 0;
  }

}
