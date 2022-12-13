<?php

declare(strict_types = 1);

namespace App\Http;

use App\DTO\Project;
use App\DTO\Release;

final class UpdateFetcher extends HttpBase
{
    public function get(string $name, string $release): Project
    {
        $url = sprintf('https://updates.drupal.org/release-history/%s/%s', $name, $release);

        return $this->request($url);
    }

    protected function parseRequest(string $content): Project
    {
        $xml = new \SimpleXMLElement($content);
        // If there is no valid project data, the XML is invalid.
        if (!isset($xml->short_name)) {
            throw new \UnexpectedValueException('Invalid XML.');
        }

        $releases = [];
        if (isset($xml->releases)) {
            foreach ($xml->releases->children() as $child) {
                $release = [];

                foreach ($child->children() as $k => $v) {
                    $release[$k] = (string) $v;
                }
                $release['terms'] = [];
                if ($child->terms) {
                    foreach ($child->terms->children() as $term) {
                        if (!isset($release['terms'][(string) $term->name])) {
                            $release['terms'][(string) $term->name] = [];
                        }
                        $release['terms'][(string) $term->name][] = (string) $term->value;
                    }
                }
                $releases[] = Release::createFromArray($release);
            }
        }
        return Project::createFromArray([
            'project_status' => (string) $xml->project_status,
            'supported_branches' => explode(',', (string) $xml->supported_branches ?? ''),
            'releases' => $releases,
        ]);
    }

    protected function getContentType(): string
    {
        return 'text/html';
    }

}
