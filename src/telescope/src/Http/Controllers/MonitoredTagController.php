<?php

declare(strict_types=1);

namespace SwooleTW\Hyperf\Telescope\Http\Controllers;

use SwooleTW\Hyperf\Http\Request;
use SwooleTW\Hyperf\Telescope\Contracts\EntriesRepository;

class MonitoredTagController
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected EntriesRepository $entries
    ) {
    }

    /**
     * Get all of the tags being monitored.
     */
    public function index(): array
    {
        return [
            'tags' => $this->entries->monitoring(),
        ];
    }

    /**
     * Begin monitoring the given tag.
     */
    public function store(Request $request): array
    {
        $this->entries->monitor([$request->input('tag')]);

        return [
            'success' => true,
        ];
    }

    /**
     * Stop monitoring the given tag.
     */
    public function destroy(Request $request): array
    {
        $this->entries->stopMonitoring([$request->input('tag')]);

        return [
            'success' => true,
        ];
    }
}
