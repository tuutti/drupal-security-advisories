<?php

declare(strict_types = 1);

namespace App\Http;

final class ProjectReleaseFetcher extends HttpBase
{
    private array $projects = [];

    private function parseProjectName(string $url) : string
    {
        [, $base, $name] = explode('/', parse_url($url, PHP_URL_PATH));

        if ($base !== 'project') {
            throw new \InvalidArgumentException('Failed to parse project name.');
        }
        return $name;
    }

    private function getPagerValue(string $url) : int
    {
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        return isset($query['page']) ? (int) $query['page'] : 0;
    }

    private function getUrl(int $page, string $releaseCategory): string
    {
        $query = http_build_query([
            'type' => 'project_release',
            'taxonomy_vocabulary_7' => 188131,
            'field_release_build_type' => 'static',
            'sort' => 'changed',
            'direction' => 'DESC',
            'page' => $page,
            'field_release_category' => $releaseCategory,
        ]);
        return sprintf('https://www.drupal.org/api-d7/node.json?%s', $query);
    }

    public function get(string $releaseCategory, int $lastChanged) : \Generator
    {
        // @todo Figure out what to do with these.
        // Fetch all releases marked as 'insecure' (tid = 188131). Some modules seem to have security
        // releases where previous releases are not marked as insecure.
        // The modules I've found so far include:
        // - drupal/die_in_twig
        // - drupal/element_embed
        // - drupal/expand_collapse_formatter
        // - drupal/google_index_api
        // - drupal/img_annotator
        // - drupal/mapbox_ui
        // - drupal/nodeaccess
        // - drupal/opigno_group_manager
        // - drupal/readonlymode.
        $content = $this->request($this->getUrl(0, $releaseCategory));
        $totalPages = $this->getPagerValue($content->last);

        for ($page = 0; $page <= $totalPages; $page++) {
            $content = $this->request($this->getUrl($page, $releaseCategory));

            foreach ($content->list as $item) {
                // Stop processing as soon as we find first unchanged item.
                if ((int) $item->changed < $lastChanged) {
                    break 2;
                }
                // Parse the project name from release URL. The URL should be something like
                // https://drupal.org/project/drupal/releases/9.4.7.
                $name = $this->parseProjectName($item->url);

                if (isset($this->projects[$name])) {
                    continue;
                }
                $this->projects[$name] = $name;

                yield $name;
            }
        }
    }

    protected function parseRequest(string $content): object
    {
        return json_decode($content);
    }

    protected function getContentType(): string
    {
        return 'application/json';
    }

}
