<?php
namespace modules\portfoliomodule\migrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Dropdown;
use craft\fields\PlainText;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\Section;
use craft\models\Section_SiteSettings;

class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->_createHomepageSection();
        $this->_createSkillCardsSection();
        $this->_createExperienceSection();
        $this->_createSocialLinksSection();
        $this->_seedDefaultEntries();
        return true;
    }

    public function safeDown(): bool
    {
        $elementsService = Craft::$app->getElements();
        $sections = ['homepage', 'skillCards', 'experience', 'socialLinks'];
        $entriesService = Craft::$app->getEntries();
        foreach ($sections as $handle) {
            $section = $entriesService->getSectionByHandle($handle);
            if ($section) {
                $entries = Entry::find()->sectionId($section->id)->status(null)->site('*')->unique()->trashed(null)->all();
                foreach ($entries as $entry) {
                    $elementsService->deleteElement($entry, true);
                }
                $entriesService->deleteSectionById($section->id);
            }
            // Hard-delete any soft-deleted sections and entry types
            Craft::$app->getDb()->createCommand()
                ->delete('{{%sections}}', ['handle' => $handle])
                ->execute();
            Craft::$app->getDb()->createCommand()
                ->delete('{{%entrytypes}}', ['handle' => $handle])
                ->execute();
        }
        $fieldHandles = [
            'heroTitle', 'heroSubtitle', 'aboutHeading', 'aboutContent', 'footerTagline',
            'iconClass', 'shortDescription', 'languagesTitle', 'languagesText', 'toolsTitle', 'toolsList',
            'company', 'role', 'period', 'expDescription', 'location',
            'platform', 'socialUrl', 'socialIconClass',
        ];
        $fieldsService = Craft::$app->getFields();
        foreach ($fieldHandles as $handle) {
            $field = $fieldsService->getFieldByHandle($handle);
            if ($field) {
                $fieldsService->deleteField($field);
            }
        }
        return true;
    }

    private function _saveField($field): void
    {
        $existing = Craft::$app->getFields()->getFieldByHandle($field->handle);
        if ($existing) {
            Craft::$app->getFields()->deleteField($existing);
        }
        if (!Craft::$app->getFields()->saveField($field)) {
            throw new \Exception('Failed to save field: ' . $field->handle . ' - ' . json_encode($field->getErrors()));
        }
    }

    private function _saveEntryType(EntryType $entryType): void
    {
        $existing = Craft::$app->getEntries()->getEntryTypeByHandle($entryType->handle);
        if ($existing) {
            Craft::$app->getEntries()->deleteEntryType($existing);
        }
        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            throw new \Exception('Failed to save entry type: ' . $entryType->handle . ' - ' . json_encode($entryType->getErrors()));
        }
    }

    private function _saveSection(Section $section): void
    {
        if (!Craft::$app->getEntries()->saveSection($section)) {
            throw new \Exception('Failed to save section: ' . $section->handle . ' - ' . json_encode($section->getErrors()));
        }
    }

    private function _createField(array $config): object
    {
        $class = $config['type'];
        $field = new $class([
            'name' => $config['name'],
            'handle' => $config['handle'],
            'instructions' => $config['instructions'] ?? '',
        ]);
        if (isset($config['options'])) $field->options = $config['options'];
        if (isset($config['multiline'])) $field->multiline = $config['multiline'];
        if (isset($config['initialRows'])) $field->initialRows = $config['initialRows'];
        if (isset($config['placeholder'])) $field->placeholder = $config['placeholder'];
        if (isset($config['charLimit'])) $field->charLimit = $config['charLimit'];
        $this->_saveField($field);
        return $field;
    }

    private function _buildFieldLayout(array $tabs): FieldLayout
    {
        $layout = new FieldLayout(['type' => Entry::class]);
        $tabArrays = [];
        foreach ($tabs as $tabName => $fields) {
            $elements = [];
            foreach ($fields as $config) {
                $field = $this->_createField($config['fieldConfig']);
                $elements[] = new CustomField($field, [
                    'label' => $config['label'] ?? null,
                    'instructions' => $config['instructions'] ?? null,
                    'required' => $config['required'] ?? false,
                ]);
            }
            $tabArrays[] = ['name' => $tabName, 'elements' => $elements];
        }
        $layout->setTabs($tabArrays);
        Craft::$app->getFields()->saveLayout($layout, false);
        return $layout;
    }

    private function _createEntryType(string $name, string $handle, FieldLayout $layout, bool $hasTitleField = true, ?string $titleFormat = null): EntryType
    {
        $entryType = new EntryType([
            'name' => $name, 'handle' => $handle,
            'hasTitleField' => $hasTitleField, 'titleFormat' => $titleFormat,
            'fieldLayoutId' => $layout->id,
        ]);
        $this->_saveEntryType($entryType);
        return $entryType;
    }

    private function _createSection(string $name, string $handle, string $type, FieldLayout $layout, bool $hasTitleField, ?string $titleFormat, array $siteOverrides = []): Section
    {
        $entryType = $this->_createEntryType($name, $handle, $layout, $hasTitleField, $titleFormat);
        $defaults = ['hasUrls' => false, 'uriFormat' => null, 'template' => null];
        $site = Craft::$app->getSites()->getPrimarySite();
        $section = new Section([
            'name' => $name, 'handle' => $handle, 'type' => $type,
            'enableVersioning' => false,
            'siteSettings' => [new Section_SiteSettings(array_merge(
                ['siteId' => $site->id, 'enabledByDefault' => true],
                $defaults, $siteOverrides,
            ))],
        ]);
        $section->setEntryTypes([$entryType]);
        $this->_saveSection($section);
        return $section;
    }

    private function _saveEntry(string $sectionHandle, array $data): void
    {
        $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);
        if (!$section) throw new \Exception("Section not found: $sectionHandle");
        $entryTypes = $section->getEntryTypes();
        if (empty($entryTypes)) throw new \Exception("No entry types for section: $sectionHandle");
        $entry = new Entry();
        $entry->sectionId = $section->id;
        $entry->typeId = $entryTypes[0]->id;
        $entry->authorId = 1;
        $entry->enabled = true;
        if (isset($data['postDate'])) {
            $entry->postDate = new \DateTime($data['postDate']);
        }
        if ($data['title'] !== null) {
            $entry->title = $data['title'];
        }
        $fields = array_filter($data['fields'] ?? [], fn($v) => $v !== null);
        $entry->setFieldValues($fields);
        try {
            if (!Craft::$app->getElements()->saveElement($entry)) {
                throw new \Exception('Failed to save entry: ' . ($data['title'] ?? '') . ' - ' . json_encode($entry->getErrors()));
            }
        } catch (\Throwable $e) {
            throw new \Exception('Error saving entry "' . ($data['title'] ?? '') . '" in section "' . $sectionHandle . '": ' . $e->getMessage() . "\nTrace:\n" . substr($e->getTraceAsString(), 0, 2000));
        }
    }

    private function _primarySite(): array
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        return ['siteId' => $site->id, 'enabledByDefault' => true];
    }

    // ========================================================================
    //  SECTIONS
    // ========================================================================

    private function _createHomepageSection(): void
    {
        $layout = $this->_buildFieldLayout([
            'Hero' => [
                ['fieldConfig' => ['type' => PlainText::class, 'name' => 'Hero Title', 'handle' => 'heroTitle', 'charLimit' => 255], 'required' => true],
                ['fieldConfig' => ['type' => PlainText::class, 'name' => 'Hero Subtitle', 'handle' => 'heroSubtitle', 'charLimit' => 255], 'required' => true],
            ],
            'About' => [
                ['fieldConfig' => ['type' => PlainText::class, 'name' => 'About Heading', 'handle' => 'aboutHeading', 'charLimit' => 255], 'required' => true],
                ['fieldConfig' => ['type' => PlainText::class, 'name' => 'About Content', 'handle' => 'aboutContent', 'multiline' => true, 'initialRows' => 4], 'required' => true],
            ],
            'Footer' => [
                ['fieldConfig' => ['type' => PlainText::class, 'name' => 'Footer Tagline', 'handle' => 'footerTagline', 'multiline' => true, 'initialRows' => 2], 'required' => false],
            ],
        ]);
        $this->_createSection('Homepage', 'homepage', Section::TYPE_SINGLE, $layout, false, '{section.name}',
            ['hasUrls' => true, 'uriFormat' => '__home__', 'template' => 'index']
        );
    }

    private function _createSkillCardsSection(): void
    {
        $layout = $this->_buildFieldLayout([
            'Content' => [
                ['fieldConfig' => ['type' => PlainText::class, 'name' => 'Icon Class', 'handle' => 'iconClass', 'instructions' => 'Font Awesome class', 'charLimit' => 100], 'required' => true],
                ['fieldConfig' => ['type' => PlainText::class, 'name' => 'Short Description', 'handle' => 'shortDescription', 'multiline' => true, 'initialRows' => 2], 'required' => true],
                ['fieldConfig' => ['type' => PlainText::class, 'name' => 'Languages Title', 'handle' => 'languagesTitle', 'charLimit' => 255], 'required' => true],
                ['fieldConfig' => ['type' => PlainText::class, 'name' => 'Languages Text', 'handle' => 'languagesText', 'multiline' => true, 'initialRows' => 2], 'required' => true],
                ['fieldConfig' => ['type' => PlainText::class, 'name' => 'Tools Title', 'handle' => 'toolsTitle', 'charLimit' => 255], 'required' => true],
                ['fieldConfig' => ['type' => PlainText::class, 'name' => 'Tools List', 'handle' => 'toolsList', 'multiline' => true, 'initialRows' => 4], 'required' => true],
            ],
        ]);
        $this->_createSection('Skill Cards', 'skillCards', Section::TYPE_CHANNEL, $layout, true, null);
    }

    private function _createExperienceSection(): void
    {
        $layout = $this->_buildFieldLayout([
            'Content' => [
                ['fieldConfig' => ['type' => PlainText::class, 'name' => 'Company', 'handle' => 'company', 'charLimit' => 255], 'required' => true],
                ['fieldConfig' => ['type' => PlainText::class, 'name' => 'Role', 'handle' => 'role', 'charLimit' => 255], 'required' => true],
                ['fieldConfig' => ['type' => PlainText::class, 'name' => 'Period', 'handle' => 'period', 'charLimit' => 255], 'required' => true],
                ['fieldConfig' => ['type' => PlainText::class, 'name' => 'Location', 'handle' => 'location', 'charLimit' => 255], 'required' => true],
                ['fieldConfig' => ['type' => PlainText::class, 'name' => 'Description', 'handle' => 'expDescription', 'multiline' => true, 'initialRows' => 6], 'required' => true],
            ],
        ]);
        $this->_createSection('Experience', 'experience', Section::TYPE_CHANNEL, $layout, true, null);
    }

    private function _createSocialLinksSection(): void
    {
        $layout = $this->_buildFieldLayout([
            'Content' => [
                ['fieldConfig' => ['type' => PlainText::class, 'name' => 'Platform', 'handle' => 'platform', 'charLimit' => 100], 'required' => true],
                ['fieldConfig' => ['type' => PlainText::class, 'name' => 'Social URL', 'handle' => 'socialUrl', 'charLimit' => 500], 'required' => true],
                ['fieldConfig' => ['type' => PlainText::class, 'name' => 'Icon Class', 'handle' => 'socialIconClass', 'instructions' => 'Font Awesome class', 'charLimit' => 100], 'required' => true],
            ],
        ]);
        $this->_createSection('Social Links', 'socialLinks', Section::TYPE_CHANNEL, $layout, true, null);
    }

    // ========================================================================
    //  DEFAULT CONTENT (seeded from CV)
    // ========================================================================

    private function _seedDefaultEntries(): void
    {
        try {
            // Clean up any leftover homepage entry that might conflict
            $oldHome = Entry::find()->uri('__home__')->status(null)->site('*')->unique()->trashed(null)->one();
            if ($oldHome) {
                Craft::$app->getElements()->deleteElement($oldHome, true);
            }
            $this->_seedHomepage();
            $this->_seedSkillCards();
            $this->_seedExperience();
            $this->_seedSocialLinks();
        } catch (\Throwable $e) {
            throw new \Exception('Seeding failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }

    private function _seedHomepage(): void
    {
        $this->_saveEntry('homepage', [
            'title' => null,
            'fields' => [
                'heroTitle' => 'Full-Stack Developer',
                'heroSubtitle' => 'I design and code beautifully simple things, and I love what I do.',
                'aboutHeading' => "Hi, I'm Carlos. Nice to meet you.",
                'aboutContent' => "Full-stack developer with 15+ years of experience building scalable applications using Laravel, Vue, React, and modern frontend technologies. Strong background in mobile development (Swift, React Native, Ionic) and end-to-end project delivery for agencies and SMEs. Based in Zürich since 2020, focused on clean architecture, maintainability, and high-impact solutions.",
                'footerTagline' => "Living, learning, & leveling up one day at a time. Based in Zürich. Building the web since 2010.",
            ],
        ]);
    }

    private function _seedSkillCards(): void
    {
        $cards = [
            [
                'title' => 'Frontend Developer',
                'iconClass' => 'fa-solid fa-display',
                'shortDescription' => 'I like to code things from scratch, and enjoy bringing ideas to life in the browser.',
                'languagesTitle' => 'Technologies I use',
                'languagesText' => 'Vue 3, React, TypeScript, Tailwind CSS',
                'toolsTitle' => 'Dev Tools',
                'toolsList' => "Vite\nNuxt\nAlpine.js\nStorybook\nFigma\nVS Code",
            ],
            [
                'title' => 'Backend Developer',
                'iconClass' => 'fa-solid fa-server',
                'shortDescription' => 'I build robust, scalable systems that power great user experiences behind the scenes.',
                'languagesTitle' => 'Technologies I use',
                'languagesText' => 'PHP, Laravel, Symfony, Craft CMS, MySQL, Redis',
                'toolsTitle' => 'Dev Tools',
                'toolsList' => "Docker\nGitHub Actions\nREST APIs\nPostman\nForge\nHorizon",
            ],
            [
                'title' => 'Mobile Developer',
                'iconClass' => 'fa-solid fa-mobile-screen',
                'shortDescription' => 'I build cross-platform mobile experiences that feel native on every device.',
                'languagesTitle' => 'Technologies I use',
                'languagesText' => 'Swift, React Native, Ionic, Angular',
                'toolsTitle' => 'Dev Tools',
                'toolsList' => "Xcode\nCordova\nCocoaPods\nFastlane\nTestFlight\nApp Center",
            ],
        ];
        foreach ($cards as $card) {
            $fields = array_filter($card, fn($k) => $k !== 'title', ARRAY_FILTER_USE_KEY);
            $this->_saveEntry('skillCards', ['title' => $card['title'], 'fields' => $fields]);
        }
    }

    private function _seedExperience(): void
    {
        $jobs = [
            [
                'title' => 'Furbo GmbH',
                'company' => 'Furbo GmbH',
                'role' => 'Fullstack Developer',
                'period' => 'Oct 2020 — Present',
                'location' => 'Zürich',
                'expDescription' => "• Developed 20+ Laravel-based web and e-commerce projects, delivering stable, scalable solutions for Swiss agencies and SMEs.\n• Built and maintained custom Laravel APIs, improving backend performance through better architecture, caching, and database optimisation.\n• Created reusable Laravel modules and internal packages that streamlined development across multiple projects.\n• Implemented modern frontends using Vue 3 and React, ensuring fast, responsive interfaces.\n• Designed solutions handling substantial traffic for client platforms, ensuring reliability and maintainability.",
            ],
            [
                'title' => 'iLovePDF',
                'company' => 'iLovePDF',
                'role' => 'App Developer',
                'period' => 'Oct 2018 — Oct 2020',
                'location' => 'Barcelona',
                'expDescription' => "• Developed the cross-platform iLovePDF mobile application using Ionic and Angular, ensuring a smooth experience across iOS and Android.\n• Built custom Cordova plugins for native functionality in Swift (iOS) and Java (Android).\n• Created and maintained WordPress plugins for image compression and watermarking.\n• Contributed to an app used by millions worldwide.\n• Key contributor to the app that won Best Productivity App in Spain (2018) with 500K+ downloads.",
            ],
            [
                'title' => 'Hopper Digital',
                'company' => 'Hopper Digital',
                'role' => 'Co-Founder & Fullstack Developer',
                'period' => 'Apr 2015 — Sep 2018',
                'location' => 'Barcelona',
                'expDescription' => "• Co-founded the agency and led 20+ projects for advertising agencies.\n• Built interactive sites with JavaScript, HTML5, CSS3.\n• Maintained Magento e-commerce for major brands (Pepe Jeans, Hackett).\n• Developed native iOS/Android apps.\n• Mentored junior developers and established development best practices.",
            ],
            [
                'title' => 'Ventignetworks',
                'company' => 'Ventignetworks',
                'role' => 'Fullstack Developer',
                'period' => 'Sep 2010 — Apr 2015',
                'location' => 'Barcelona',
                'expDescription' => "• Delivered landing pages and web projects for major agencies (Grey, Havas, Saatchi).\n• Built backend systems with Laravel, Symfony, Phalcon.\n• Created WordPress themes and plugins for client projects.\n• Contributed to Ticketmaster Spain platform maintenance.\n• Gained solid foundation in full-stack development and client communication.",
            ],
        ];
        $postDates = ['2024-01-01 00:00:00', '2022-01-01 00:00:00', '2020-01-01 00:00:00', '2018-01-01 00:00:00'];
        foreach ($jobs as $i => $job) {
            $fields = array_filter($job, fn($k) => $k !== 'title', ARRAY_FILTER_USE_KEY);
            $this->_saveEntry('experience', ['title' => $job['title'], 'postDate' => $postDates[$i], 'fields' => $fields]);
        }
    }

    private function _seedSocialLinks(): void
    {
        $links = [
            ['title' => 'LinkedIn', 'platform' => 'LinkedIn', 'socialUrl' => 'https://www.linkedin.com/in/carlosmartinespinosa/', 'socialIconClass' => 'fab fa-linkedin-in'],
            ['title' => 'Email', 'platform' => 'Email', 'socialUrl' => 'mailto:cmartinespinosa@gmail.com', 'socialIconClass' => 'far fa-envelope'],
        ];
        foreach ($links as $l) {
            $fields = array_filter($l, fn($k) => $k !== 'title', ARRAY_FILTER_USE_KEY);
            $this->_saveEntry('socialLinks', ['title' => $l['title'], 'fields' => $fields]);
        }
    }
}
