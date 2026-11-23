<?php

use Illuminate\Database\Schema\Builder;

/*
 * A starting catalogue an admin can rename, extend or empty. Only on a fresh
 * install: a catalogue that already has categories is left alone.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        if ($db->table('dossier_skill_categories')->exists()) {
            return;
        }

        $catalogue = [
            'Programming languages' => ['PHP', 'JavaScript', 'TypeScript', 'Python', 'Go', 'Rust', 'Java', 'C#'],
            'Backend' => ['Laravel', 'Symfony', 'Node.js', 'REST APIs', 'MySQL', 'PostgreSQL', 'Redis', 'Docker'],
            'Frontend' => ['HTML & CSS', 'React', 'Vue', 'Mithril', 'Tailwind CSS', 'Accessibility'],
        ];
        $now = date('Y-m-d H:i:s');
        $position = 0;

        foreach ($catalogue as $category => $skills) {
            $id = $db->table('dossier_skill_categories')->insertGetId(['name' => $category, 'position' => $position++]);

            foreach ($skills as $skill) {
                $db->table('dossier_skills')->insert([
                    'name' => $skill,
                    'slug' => mb_strtolower($skill),
                    'category_id' => $id,
                    'approved' => true,
                    'created_at' => $now,
                ]);
            }
        }
    },
    'down' => function (Builder $schema) {
    },
];
