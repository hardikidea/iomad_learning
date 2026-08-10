<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\local\ui;

use html_writer;
use moodle_url;

/**
 * Validated state shared by administration list pages.
 *
 * Sort expressions are selected only from a caller-supplied allow-list. They must never contain
 * request data. Search text is returned separately for use with Moodle DML placeholders.
 *
 * @package local_orgprofile
 */
final class listing {

    /** Allowed page sizes. */
    public const PAGE_SIZES = [10, 20, 50, 100];

    /** @var string Search text. */
    private string $query;

    /** @var int Zero-based page. */
    private int $page;

    /** @var int Rows per page. */
    private int $perpage;

    /** @var string Selected sort key. */
    private string $sort;

    /** @var string Sort direction. */
    private string $direction;

    /** @var array<string, string> Trusted sort key to SQL expression map. */
    private array $sortfields;

    /**
     * Construct validated listing state.
     *
     * @param array<string, string> $sortfields Trusted sort key to SQL expression map
     * @param string $defaultsort Default sort key
     * @param string $query Search text
     * @param int $page Zero-based page
     * @param int $perpage Rows per page
     * @param string $sort Requested sort key
     * @param string $direction Requested direction
     */
    public function __construct(
        array $sortfields,
        string $defaultsort,
        string $query = '',
        int $page = 0,
        int $perpage = 20,
        string $sort = '',
        string $direction = 'asc'
    ) {
        if (!array_key_exists($defaultsort, $sortfields)) {
            throw new \coding_exception('The default sort key must be allow-listed.');
        }
        $this->sortfields = $sortfields;
        $this->query = trim(clean_param($query, PARAM_TEXT));
        $this->page = max(0, $page);
        $this->perpage = in_array($perpage, self::PAGE_SIZES, true) ? $perpage : 20;
        $this->sort = array_key_exists($sort, $sortfields) ? $sort : $defaultsort;
        $this->direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
    }

    /** Create state from validated request parameters. */
    public static function from_request(array $sortfields, string $defaultsort): self {
        return new self(
            $sortfields,
            $defaultsort,
            optional_param('q', '', PARAM_TEXT),
            optional_param('page', 0, PARAM_INT),
            optional_param('perpage', 20, PARAM_INT),
            optional_param('sort', '', PARAM_ALPHANUMEXT),
            optional_param('dir', 'asc', PARAM_ALPHA)
        );
    }

    /** Search text for a DML placeholder. */
    public function query(): string {
        return $this->query;
    }

    /** Zero-based page. */
    public function page(): int {
        return $this->page;
    }

    /** Rows per page. */
    public function perpage(): int {
        return $this->perpage;
    }

    /** DML record offset. */
    public function offset(): int {
        return $this->page * $this->perpage;
    }

    /** Trusted SQL ORDER BY fragment. */
    public function order_by(): string {
        return $this->sortfields[$this->sort] . ' ' . strtoupper($this->direction);
    }

    /** Parameters to preserve when moving between listing pages. */
    public function url_params(bool $includepage = false): array {
        $params = [
            'perpage' => $this->perpage,
            'sort' => $this->sort,
            'dir' => $this->direction,
        ];
        if ($this->query !== '') {
            $params['q'] = $this->query;
        }
        if ($includepage) {
            $params['page'] = $this->page;
        }
        return $params;
    }

    /** Data used to initialise the filter form. */
    public function filter_data(): array {
        return ['q' => $this->query, 'perpage' => $this->perpage];
    }

    /** Render a sortable table heading. */
    public function heading(string $key, string $label, moodle_url $baseurl): string {
        if (!array_key_exists($key, $this->sortfields)) {
            return s($label);
        }
        $nextdirection = $this->sort === $key && $this->direction === 'asc' ? 'desc' : 'asc';
        $url = new moodle_url($baseurl, $this->url_params());
        $url->params(['sort' => $key, 'dir' => $nextdirection, 'page' => 0]);
        $indicator = '';
        if ($this->sort === $key) {
            $indicator = $this->direction === 'asc' ? ' ↑' : ' ↓';
        }
        return html_writer::link($url, s($label . $indicator), [
            'class' => 'text-nowrap',
            'title' => get_string('sortby', 'local_orgprofile', $label),
        ]);
    }
}
