<?php

namespace App\Scraping\Http;

/**
 * A robots.txt parser following RFC 9309: groups per user-agent, the most
 * specific group wins, and within it the longest matching rule (allow on ties).
 */
final class RobotsTxt
{
    /**
     * @param  list<array{agents: list<string>, rules: list<array{allow: bool, pattern: string}>}>  $groups
     */
    private function __construct(private readonly array $groups) {}

    public static function parse(string $content): self
    {
        $groups = [];
        $previousWasAgent = false;

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*/', '', $line));

            if (! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map(trim(...), explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                // Consecutive user-agent lines share the rules that follow them.
                if (! $previousWasAgent || $groups === []) {
                    $groups[] = ['agents' => [], 'rules' => []];
                }
                $groups[array_key_last($groups)]['agents'][] = strtolower($value);
                $previousWasAgent = true;

                continue;
            }

            $previousWasAgent = false;

            if (in_array($field, ['allow', 'disallow'], true) && $groups !== [] && $value !== '') {
                $groups[array_key_last($groups)]['rules'][] = ['allow' => $field === 'allow', 'pattern' => $value];
            }
        }

        return new self($groups);
    }

    public static function disallowAll(): self
    {
        return new self([['agents' => ['*'], 'rules' => [['allow' => false, 'pattern' => '/']]]]);
    }

    /**
     * @param  string  $path  Path plus query string, e.g. "/list?page=2".
     */
    public function allows(string $userAgent, string $path): bool
    {
        $verdict = null;
        $longest = -1;

        foreach ($this->rulesFor(strtolower($userAgent)) as $rule) {
            if (! $this->matches($rule['pattern'], $path)) {
                continue;
            }

            $length = strlen($rule['pattern']);

            if ($length > $longest || ($length === $longest && $rule['allow'])) {
                $verdict = $rule['allow'];
                $longest = $length;
            }
        }

        return $verdict ?? true;
    }

    /**
     * @return list<array{allow: bool, pattern: string}>
     */
    private function rulesFor(string $userAgent): array
    {
        $specific = [];
        $specificLength = 0;
        $wildcard = [];

        foreach ($this->groups as $group) {
            foreach ($group['agents'] as $agent) {
                if ($agent === '*') {
                    $wildcard = [...$wildcard, ...$group['rules']];
                } elseif (str_contains($userAgent, $agent) && strlen($agent) >= $specificLength) {
                    $specific = strlen($agent) > $specificLength ? $group['rules'] : [...$specific, ...$group['rules']];
                    $specificLength = strlen($agent);
                }
            }
        }

        return $specificLength > 0 ? $specific : $wildcard;
    }

    private function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $body = $anchored ? substr($pattern, 0, -1) : $pattern;
        $regex = '#^'.str_replace('\*', '.*', preg_quote($body, '#')).($anchored ? '$' : '').'#';

        return preg_match($regex, $path) === 1;
    }
}
