<?php

namespace Ernestdefoe\Kindred\Console;

use Ernestdefoe\Kindred\WordIndex;
use Flarum\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * `php flarum kindred:index` — index the titles that aren't yet, such as those
 * started while Kindred was disabled. `--fresh` rebuilds every one.
 */
class IndexCommand extends AbstractCommand
{
    public function __construct(protected WordIndex $index)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('kindred:index')
            ->setDescription('Index discussion titles for Kindred\'s similar-discussions matching.')
            ->addOption('fresh', null, InputOption::VALUE_NONE, 'Rebuild the index for every discussion.');
    }

    protected function fire(): int
    {
        $count = $this->index->fill((bool) $this->input->getOption('fresh'));
        $this->info("Indexed $count discussion title(s).");

        return 0;
    }
}
