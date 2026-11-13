<?php

namespace Ernestdefoe\Dossier;

use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;

/**
 * Checks what members send before it is stored: known fields only, trimmed,
 * capped in length, dates as "YYYY-MM", URLs allow-listed. Nothing else in a
 * request body reaches the database (no user_id, no position, no image).
 */
class Input
{
    public const CEFR = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'native'];

    public function __construct(protected TranslatorInterface $translator)
    {
    }

    /**
     * The About fields, open-to chips and section visibility that came in; keys not sent are left out.
     *
     * @param array<string, mixed> $in
     * @param list<string> $openToIds
     * @return array<string, mixed>
     */
    public function about(array $in, array $openToIds): array
    {
        $out = [];

        foreach (['headline' => 150, 'role' => 150] as $key => $max) {
            if (array_key_exists($key, $in)) {
                $out[$key] = $this->line($in[$key], $max, $key);
            }
        }

        if (array_key_exists('bio', $in)) {
            $out['bio'] = $this->text($in['bio'], 3000, 'bio');
        }

        foreach (['specializations', 'interests'] as $key) {
            if (array_key_exists($key, $in)) {
                $out[$key] = $this->tags($in[$key], $key);
            }
        }

        if (array_key_exists('openTo', $in)) {
            $chosen = is_array($in['openTo']) ? array_values(array_unique(array_filter($in['openTo'], 'is_string'))) : [];
            $out['open_to'] = array_values(array_intersect($chosen, $openToIds)) ?: null;
        }

        if (array_key_exists('visibility', $in)) {
            $visibility = [];

            foreach (is_array($in['visibility']) ? $in['visibility'] : [] as $section => $level) {
                if (in_array($section, Visibility::SECTIONS, true)) {
                    if (! in_array($level, Visibility::LEVELS, true)) {
                        $this->fail('visibility', 'invalid_visibility');
                    }
                    $visibility[$section] = $level;
                }
            }

            $out['visibility'] = $visibility;
        }

        return $out;
    }

    /**
     * An entry's fields for its section. Skills are resolved by the caller.
     *
     * @param array<string, mixed> $in
     * @return array<string, mixed>
     */
    public function entry(string $section, array $in): array
    {
        return match ($section) {
            'timeline' => $this->timeline($in),
            'languages' => [
                'name' => $this->required($this->line($in['name'] ?? null, 60, 'name'), 'name'),
                'level' => in_array($in['level'] ?? null, self::CEFR, true) ? $in['level'] : $this->fail('level', 'invalid_level'),
                'percent' => $this->percent($in['percent'] ?? null, 'percent', true),
            ],
            'certificates' => [
                'title' => $this->required($this->line($in['title'] ?? null, 150, 'title'), 'title'),
                'issuer' => $this->line($in['issuer'] ?? null, 120, 'issuer'),
                'issued' => $this->month($in['issued'] ?? null, 'issued'),
                'url' => $this->url($in['url'] ?? null, 'url', false),
            ],
            'links' => $this->link($in),
            'skills' => ['level' => $this->percent($in['level'] ?? null, 'level', false)],
            default => $this->fail('section', 'invalid_section'),
        };
    }

    /**
     * @param array<string, mixed> $in
     * @return array<string, mixed>
     */
    private function timeline(array $in): array
    {
        $start = $this->required($this->month($in['start'] ?? null, 'start'), 'start');
        $ongoing = (bool) ($in['ongoing'] ?? false);
        $end = $ongoing ? null : $this->required($this->month($in['end'] ?? null, 'end'), 'end');

        if ($end !== null && $end < $start) {
            $this->fail('end', 'end_before_start');
        }

        return [
            'kind' => in_array($in['kind'] ?? null, ['work', 'education'], true) ? $in['kind'] : $this->fail('kind', 'invalid_kind'),
            'title' => $this->required($this->line($in['title'] ?? null, 120, 'title'), 'title'),
            'organization' => $this->line($in['organization'] ?? null, 120, 'organization'),
            'location' => $this->line($in['location'] ?? null, 120, 'location'),
            'start' => $start,
            'end' => $end,
            'description' => $this->text($in['description'] ?? null, 1500, 'description'),
        ];
    }

    /**
     * @param array<string, mixed> $in
     * @return array<string, mixed>
     */
    private function link(array $in): array
    {
        $kind = $in['kind'] ?? null;

        if (! is_string($kind) || ! array_key_exists($kind, Url::KINDS)) {
            $this->fail('kind', 'invalid_kind');
        }

        return [
            'kind' => $kind,
            'url' => $this->url($in['url'] ?? null, 'url', true),
            'label' => $this->line($in['label'] ?? null, 60, 'label'),
            'visibility' => in_array($in['visibility'] ?? Visibility::EVERYONE, Visibility::LEVELS, true)
                ? ($in['visibility'] ?? Visibility::EVERYONE)
                : $this->fail('visibility', 'invalid_visibility'),
        ];
    }

    /**
     * A list of ids, each once, in the order sent; at most $max.
     *
     * @return list<int>
     */
    public function ids(mixed $value, string $field, int $max): array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value) || array_filter($value, fn ($id) => ! is_int($id) && ! (is_string($id) && ctype_digit($id)))) {
            $this->fail($field, 'invalid');
        }

        $ids = array_values(array_unique(array_map('intval', $value)));

        if (count($ids) > $max) {
            $this->fail($field, 'too_many', ['max' => $max]);
        }

        return $ids;
    }

    public function line(mixed $value, int $max, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) && ! is_numeric($value)) {
            $this->fail($field, 'invalid');
        }

        $value = trim((string) preg_replace('/[\p{Cc}\p{Cf}\s]+/u', ' ', (string) $value));

        if (mb_strlen($value) > $max) {
            $this->fail($field, 'too_long', ['max' => $max]);
        }

        return $value === '' ? null : $value;
    }

    /** Several lines of plain text: line breaks kept (at most two in a row), other control characters dropped. */
    private function text(mixed $value, int $max, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            $this->fail($field, 'invalid');
        }

        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = (string) preg_replace('/[^\P{C}\n]+/u', '', $value);
        $value = trim((string) preg_replace("/\n{3,}/", "\n\n", $value));

        if (mb_strlen($value) > $max) {
            $this->fail($field, 'too_long', ['max' => $max]);
        }

        return $value === '' ? null : $value;
    }

    /** @return list<string>|null */
    private function tags(mixed $value, string $field): ?array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            $this->fail($field, 'invalid');
        }

        $tags = [];

        foreach ($value as $tag) {
            $tag = $this->line(is_string($tag) ? $tag : null, 40, $field);

            if ($tag !== null && ! in_array(mb_strtolower($tag), array_map('mb_strtolower', $tags), true)) {
                $tags[] = $tag;
            }
        }

        if (count($tags) > 20) {
            $this->fail($field, 'too_many', ['max' => 20]);
        }

        return $tags ?: null;
    }

    /** "YYYY-MM", within a sensible range. */
    private function month(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})$/', $value, $m)
            || (int) $m[2] < 1 || (int) $m[2] > 12 || (int) $m[1] < 1900 || (int) $m[1] > (int) date('Y') + 10) {
            $this->fail($field, 'invalid_month');
        }

        return $value;
    }

    private function percent(mixed $value, string $field, bool $optional): ?int
    {
        if ($optional && ($value === null || $value === '')) {
            return null;
        }

        if (! is_numeric($value) || (int) $value != $value || $value < 0 || $value > 100) {
            $this->fail($field, 'invalid_percent');
        }

        return (int) $value;
    }

    private function url(mixed $value, string $field, bool $required): ?string
    {
        if (! $required && ($value === null || $value === '')) {
            return null;
        }

        return Url::safe($value) ?? $this->fail($field, 'invalid_url');
    }

    private function required(mixed $value, string $field): mixed
    {
        return $value ?? $this->fail($field, 'required');
    }

    /** @param array<string, mixed> $params */
    public function fail(string $field, string $key, array $params = []): never
    {
        throw new ValidationException([$field => $this->translator->trans("ernestdefoe-dossier.api.$key", $params)]);
    }
}
