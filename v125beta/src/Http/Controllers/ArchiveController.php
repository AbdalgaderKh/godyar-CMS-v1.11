<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\FrontendRenderer;
use App\Http\Presenters\SeoPresenter;
use Godyar\Services\NewsService;

final class ArchiveController
{
    /** @var NewsService */
    private NewsService $news;
    /** @var FrontendRenderer */
    private FrontendRenderer $view;
    /** @var SeoPresenter */
    private SeoPresenter $seo;

    public function __construct(NewsService $news, FrontendRenderer $view, SeoPresenter $seo)
    {
        $this->news = $news;
        $this->view = $view;
        $this->seo = $seo;
    }

    public function index(int $page = 1, ?int $year = null, ?int $month = null): void
    {
        $page = max(1, $page);
        $perPage = 12;

        // basic sanity
        if ($year !== null && ($year < 1970 || $year > 2100)) {
            $year = null;
        }
        if ($month !== null && ($month < 1 || $month > 12)) {
            $month = null;
        }



        // output cache (anonymous GET only)
        $__oc = (function_exists('gdy_output_cache_begin') === TRUE)
            ? gdy_output_cache_begin('archive', [
                'path' => (string)gdy_request_path(),
                'page' => (int)$page,
                'year' => (int)($year ?? 0),
                'month' => (int)($month ?? 0),
            ])
            : ['served' => FALSE, 'did' => FALSE, 'key' => '', 'ttl' => 0];

        if ((isset($__oc['served']) === TRUE) && ($__oc['served'] === TRUE)) {
            return;
        }

        $list = $this->news->archive($page, $perPage, $year, $month);

        $title = 'الأرشيف';
        if ((empty($year) === false)) {
            $title .= ' - ' . $year;
            if ((empty($month) === false)) {
                $title .= '-' . str_pad((string)$month, 2, '0', STR_PAD_LEFT);
            }
        }

        $this->view->render(
            'frontend/views/archive.php',
            [
                'items' => $list['items'],
                'page' => $page,
                'pages' => $list['total_pages'],
                'page_title' => $title,
                'year' => $year,
                'month' => $month,
            ],
            [
                'pageSeo' => $this->seo->archive($year, $month),
            ]
        );
        if (function_exists('gdy_output_cache_end') === TRUE) {
            gdy_output_cache_end($__oc);
        }

    }
}