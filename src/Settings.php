<?php

namespace Ernestdefoe\Dossier;

use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;

/** Dossier's settings, read in one place and checked on the way out. */
class Settings
{
    public const DEFAULT_TAB = 'ernestdefoe-dossier.default_tab';

    public const CUSTOM_SKILLS = 'ernestdefoe-dossier.custom_skills';

    public const OPEN_TO = 'ernestdefoe-dossier.open_to';

    /** The chips a fresh install offers; their names come from the locale until the admin renames them. */
    public const OPEN_TO_DEFAULT = '[{"id":"collaboration"},{"id":"mentoring"},{"id":"freelance"},{"id":"jobs"}]';

    public const BUILT_IN = ['collaboration', 'mentoring', 'freelance', 'jobs'];

    public function __construct(protected SettingsRepositoryInterface $settings, protected TranslatorInterface $translator)
    {
    }

    public function customSkills(): bool
    {
        return (bool) $this->settings->get(self::CUSTOM_SKILLS, true);
    }

    /**
     * The "Open to" chips the admin offers, each with the name readers see.
     *
     * @return list<array{id: string, label: string}>
     */
    public function openTo(): array
    {
        $decoded = json_decode((string) $this->settings->get(self::OPEN_TO, self::OPEN_TO_DEFAULT), true);
        $options = [];

        foreach (is_array($decoded) ? $decoded : [] as $option) {
            $id = is_array($option) ? (string) ($option['id'] ?? '') : '';
            $label = trim(mb_substr((string) ($option['label'] ?? ''), 0, 60));

            if (! preg_match('/^[a-z0-9_-]{1,40}$/', $id) || isset($options[$id])) {
                continue;
            }

            if ($label === '') {
                if (! in_array($id, self::BUILT_IN, true)) {
                    continue;
                }

                $label = $this->translator->trans("ernestdefoe-dossier.lib.open_to.$id");
            }

            $options[$id] = ['id' => $id, 'label' => $label];
        }

        return array_values(array_slice($options, 0, 30));
    }
}
