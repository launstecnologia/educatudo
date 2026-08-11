<?php

require_once __DIR__ . '/../../Core/LayoutHelper.php';

class PwaController
{
    /**
     * Constrói o manifest PWA.
     * O campo "id" é obrigatório para instalar Aluno, Professor e Admin como apps separados:
     * sem id único, o sistema operacional trata todos como o mesmo app ("já instalado").
     */
    private function buildManifest(array $options)
    {
        $name = $options['name'] ?? 'EducaTudo';
        $shortName = $options['short_name'] ?? $name;
        $startUrl = $options['start_url'] ?? '/';
        $scope = $options['scope'] ?? '/';
        $id = $options['id'] ?? null;
        $themeColor = $options['theme_color'] ?? '#a855f7';
        $backgroundColor = $options['background_color'] ?? '#ffffff';
        $icon192 = $options['icon_192'] ?? '';
        $icon512 = $options['icon_512'] ?? '';

        $icons = [];
        if (!empty($icon192)) {
            $icons[] = [
                'src' => $icon192,
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any'
            ];
        }
        if (!empty($icon512)) {
            $icons[] = [
                'src' => $icon512,
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any'
            ];
        }

        $manifest = [
            'name' => $name,
            'short_name' => $shortName,
            'start_url' => $startUrl,
            'scope' => $scope,
            'display' => 'standalone',
            'theme_color' => $themeColor,
            'background_color' => $backgroundColor,
            'icons' => $icons
        ];

        // id único: permite instalar Aluno, Professor e Admin separadamente na área de trabalho
        if ($id !== null) {
            $manifest['id'] = $id;
        }

        return $manifest;
    }

    private function outputManifest(array $manifest)
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function manifestAluno()
    {
        $defaultIcon = LayoutHelper::getLogo1x1Url();
        $manifest = $this->buildManifest([
            'id' => '/aluno',
            'name' => LayoutHelper::get('pwa_aluno_name', 'EducaTudo Aluno'),
            'short_name' => LayoutHelper::get('pwa_aluno_short_name', 'Aluno'),
            'start_url' => '/?source=pwa',
            'scope' => '/',
            'theme_color' => LayoutHelper::get('pwa_aluno_theme_color', LayoutHelper::get('primary_color', '#a855f7')),
            'background_color' => LayoutHelper::get('pwa_aluno_background_color', '#ffffff'),
            'icon_192' => LayoutHelper::get('pwa_aluno_icon_192', $defaultIcon),
            'icon_512' => LayoutHelper::get('pwa_aluno_icon_512', $defaultIcon)
        ]);

        $this->outputManifest($manifest);
    }

    public function manifestProfessor()
    {
        $defaultIcon = LayoutHelper::getLogo1x1Url();
        $manifest = $this->buildManifest([
            'id' => '/professor',
            'name' => LayoutHelper::get('pwa_professor_name', 'EducaTudo Professor'),
            'short_name' => LayoutHelper::get('pwa_professor_short_name', 'Professor'),
            'start_url' => '/professor?source=pwa',
            'scope' => '/professor',
            'theme_color' => LayoutHelper::get('pwa_professor_theme_color', LayoutHelper::get('primary_color', '#a855f7')),
            'background_color' => LayoutHelper::get('pwa_professor_background_color', '#ffffff'),
            'icon_192' => LayoutHelper::get('pwa_professor_icon_192', $defaultIcon),
            'icon_512' => LayoutHelper::get('pwa_professor_icon_512', $defaultIcon)
        ]);

        $this->outputManifest($manifest);
    }

    public function manifestAdmin()
    {
        $defaultIcon = LayoutHelper::getLogo1x1Url();
        $manifest = $this->buildManifest([
            'id' => '/admin',
            'name' => LayoutHelper::get('pwa_admin_name', 'EducaTudo Admin'),
            'short_name' => LayoutHelper::get('pwa_admin_short_name', 'Admin'),
            'start_url' => '/admin?source=pwa',
            'scope' => '/admin',
            'theme_color' => LayoutHelper::get('pwa_admin_theme_color', LayoutHelper::get('primary_color', '#a855f7')),
            'background_color' => LayoutHelper::get('pwa_admin_background_color', '#ffffff'),
            'icon_192' => LayoutHelper::get('pwa_admin_icon_192', $defaultIcon),
            'icon_512' => LayoutHelper::get('pwa_admin_icon_512', $defaultIcon)
        ]);

        $this->outputManifest($manifest);
    }

    /**
     * Manifest PWA para área dos Pais (instalável como app separado)
     */
    public function manifestPais()
    {
        $defaultIcon = LayoutHelper::getLogo1x1Url();
        $manifest = $this->buildManifest([
            'id' => '/pais',
            'name' => LayoutHelper::get('pwa_pais_name', 'EducaTudo Pais'),
            'short_name' => LayoutHelper::get('pwa_pais_short_name', 'Pais'),
            'start_url' => '/pais?source=pwa',
            'scope' => '/pais',
            'theme_color' => LayoutHelper::get('pwa_pais_theme_color', LayoutHelper::get('primary_color', '#a855f7')),
            'background_color' => LayoutHelper::get('pwa_pais_background_color', '#ffffff'),
            'icon_192' => LayoutHelper::get('pwa_pais_icon_192', $defaultIcon),
            'icon_512' => LayoutHelper::get('pwa_pais_icon_512', $defaultIcon)
        ]);

        $this->outputManifest($manifest);
    }
}

