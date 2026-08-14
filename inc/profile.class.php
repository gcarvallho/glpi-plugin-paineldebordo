<?php

/**
 * Rights for Painel de Bordo (plugin dashboard) — GLPI Profiles tab.
 */
class PluginPaineldebordoProfile extends Profile
{
    public static $rightname = 'profile';

    /**
     * READ + UPDATE with distinct short/long labels (analysis / resources).
     *
     * @return array<int,array{short:string,long:string}>
     */
    public static function getReadUpdateRights(string $readLong, string $updateShort, string $updateLong): array
    {
        return [
            READ => [
                'short' => __('Read', 'paineldebordo'),
                'long'  => $readLong,
            ],
            UPDATE => [
                'short' => $updateShort,
                'long'  => $updateLong,
            ],
        ];
    }

    /**
     * @return array<int,array{short:string,long:string}>
     */
    public static function getReadOnlyRights(string $long): array
    {
        return [
            READ => [
                'short' => __('Read', 'paineldebordo'),
                'long'  => $long,
            ],
        ];
    }

    public static function getAllRights($all = false)
    {
        return [
            [
                'itemtype' => 'PluginPaineldebordoConfig',
                'label'    => __('Panel access', 'paineldebordo'),
                'field'    => 'plugin_paineldebordo',
                'rights'   => self::getReadOnlyRights(
                    __('Enter the app (Overview and TV mode).', 'paineldebordo')
                ),
            ],
            [
                'label'  => __('Wide group vision', 'paineldebordo'),
                'field'  => 'plugin_paineldebordo_groups',
                'rights' => self::getReadOnlyRights(
                    __('See all groups in ticket filters and TV (not only your groups).', 'paineldebordo')
                ),
            ],
            [
                'label'  => __('Tickets', 'paineldebordo'),
                'field'  => 'plugin_paineldebordo_tickets',
                'rights' => self::getReadOnlyRights(
                    __('Access the Tickets module.', 'paineldebordo')
                ),
            ],
            [
                'label'  => __('Analysis', 'paineldebordo'),
                'field'  => 'plugin_paineldebordo_analysis',
                'rights' => self::getReadUpdateRights(
                    __('Access Charts, Reports and BI.', 'paineldebordo'),
                    __('Edit layouts', 'paineldebordo'),
                    __('Save BI and Overview mural layouts.', 'paineldebordo')
                ),
            ],
            [
                'label'  => __('Resources', 'paineldebordo'),
                'field'  => 'plugin_paineldebordo_resources',
                'rights' => self::getReadUpdateRights(
                    __('Access Map and Assets.', 'paineldebordo'),
                    __('Edit map', 'paineldebordo'),
                    __('Edit map coordinates.', 'paineldebordo')
                ),
            ],
        ];
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile && $item->getField('interface') === 'central') {
            return self::createTabEntry(__('Painel de Bordo', 'paineldebordo'));
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Profile) {
            $profile = new self();
            $profile->showForm($item->getID());
        }

        return true;
    }

    public function showForm($profiles_id = 0, array $options = [])
    {
        $profile = new Profile();
        $profile->getFromDB($profiles_id);

        echo "<div class='spaced'>";
        if ($canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE])) {
            echo "<form method='post' action='" . $profile->getFormURL() . "'>";
        }

        echo "<div class='alert alert-info' style='margin-bottom:1rem;'>";
        echo '<p><strong>' . htmlspecialchars(__('How rights work', 'paineldebordo')) . '</strong></p>';
        echo '<ul style="margin:0.35rem 0 0;padding-left:1.25rem;">';
        echo '<li>' . htmlspecialchars(
            __('Configuration / Admin is not this matrix — only Super-Admin (or GLPI config UPDATE).', 'paineldebordo')
        ) . '</li>';
        echo '<li>' . htmlspecialchars(
            __('Wide group vision is a separate right; without it the user only sees their groups.', 'paineldebordo')
        ) . '</li>';
        echo '<li>' . htmlspecialchars(
            __('Analysis/Resources Update means edit layouts / edit map — not see all tickets.', 'paineldebordo')
        ) . '</li>';
        echo '</ul></div>';

        $rights = self::getAllRights();
        $profile->displayRightsChoiceMatrix($rights, [
            'canedit' => $canedit,
            'title'   => __('Painel de Bordo', 'paineldebordo'),
        ]);

        if ($canedit) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $profiles_id]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>\n";
            Html::closeForm();
        }
        echo '</div>';

        return true;
    }

    public static function addDefaultProfileInfos($profiles_id, array $rights)
    {
        $profileRight = new ProfileRight();
        foreach ($rights as $name => $right) {
            if (
                countElementsInTable('glpi_profilerights', [
                    'profiles_id' => $profiles_id,
                    'name'        => $name,
                ]) === 0
            ) {
                $profileRight->add([
                    'profiles_id' => $profiles_id,
                    'name'        => $name,
                    'rights'      => $right,
                ]);
            }
            if (isset($_SESSION['glpiactiveprofile']['id'])
                && (int) $_SESSION['glpiactiveprofile']['id'] === (int) $profiles_id
            ) {
                $_SESSION['glpiactiveprofile'][$name] = $right;
            }
        }
    }

    /**
     * Full seed for Super-Admin (includes wide group vision).
     */
    public static function createFirstAccess($profiles_id)
    {
        self::addDefaultProfileInfos($profiles_id, [
            'plugin_paineldebordo'           => READ,
            'plugin_paineldebordo_groups'    => READ,
            'plugin_paineldebordo_tickets'   => READ,
            'plugin_paineldebordo_analysis'  => READ | UPDATE,
            'plugin_paineldebordo_resources' => READ | UPDATE,
        ]);
    }

    /**
     * Minimal seed: enter app only (no modules, no wide vision).
     */
    public static function createMinimalAccess($profiles_id)
    {
        self::addDefaultProfileInfos($profiles_id, [
            'plugin_paineldebordo'        => READ,
            'plugin_paineldebordo_groups' => 0,
            'plugin_paineldebordo_tickets' => 0,
            'plugin_paineldebordo_analysis' => 0,
            'plugin_paineldebordo_resources' => 0,
        ]);
    }

    /**
     * Ensure ProfileRight rows exist. Must never throw — may run on every request.
     * Does not overwrite existing admin choices. Does not copy master UPDATE → wide vision.
     */
    public static function migrateModuleRights(): void
    {
        global $DB;

        try {
            if (!$DB->TableExists('glpi_profilerights')) {
                return;
            }

            // Do NOT call ProfileRight::addProfileRights() — GLPI 11 INSERT is not
            // idempotent and throws Duplicate entry 1062, aborting plugin load.

            $profiles = $DB->request([
                'SELECT' => ['id', 'name'],
                'FROM'   => 'glpi_profiles',
            ]);

            foreach ($profiles as $prow) {
                $pid = (int) $prow['id'];
                $pname = (string) ($prow['name'] ?? '');
                $isSuper = ($pname === 'Super-Admin' || $pname === 'Super-Administrador');

                $master = 0;
                $it = $DB->request([
                    'SELECT' => ['rights'],
                    'FROM'   => 'glpi_profilerights',
                    'WHERE'  => [
                        'profiles_id' => $pid,
                        'name'        => 'plugin_paineldebordo',
                    ],
                    'LIMIT'  => 1,
                ]);
                foreach ($it as $r) {
                    $master = (int) $r['rights'];
                }
                if (($master & READ) !== READ) {
                    continue;
                }

                // Wide group vision row — default 0; Super-Admin gets READ if missing
                self::ensureRightRow($pid, 'plugin_paineldebordo_groups', $isSuper ? READ : 0);

                // Modules: missing rows default to 0 (Overview-only). Legacy rows kept as-is.
                // Super-Admin missing modules get full seed bits.
                $moduleDefaults = [
                    'plugin_paineldebordo_tickets'   => $isSuper ? READ : 0,
                    'plugin_paineldebordo_analysis'  => $isSuper ? (READ | UPDATE) : 0,
                    'plugin_paineldebordo_resources' => $isSuper ? (READ | UPDATE) : 0,
                ];
                foreach ($moduleDefaults as $field => $bits) {
                    self::ensureRightRow($pid, $field, $bits);
                }
            }
        } catch (Throwable $e) {
            if (class_exists('Toolbox') && method_exists('Toolbox', 'logInFile')) {
                Toolbox::logInFile(
                    'paineldebordo',
                    'migrateModuleRights: ' . $e->getMessage() . "\n"
                );
            }
        }
    }

    private static function ensureRightRow(int $profiles_id, string $name, int $rights): void
    {
        $exists = countElementsInTable('glpi_profilerights', [
            'profiles_id' => $profiles_id,
            'name'        => $name,
        ]);
        if ($exists > 0) {
            return;
        }
        $pr = new ProfileRight();
        $pr->add([
            'profiles_id' => $profiles_id,
            'name'        => $name,
            'rights'      => $rights,
        ]);
    }

    /**
     * Sync current profile rights into the session.
     * Must stay cheap: called from plugin_init / change_profile on every request.
     * Full ACL migrate (all profiles) belongs in install/upgrade only — running it
     * here hung Config → Plugins disable (session/DB work while deactivate ran).
     */
    public static function initProfile()
    {
        global $DB;

        if (!isset($_SESSION['glpiactiveprofile']['id'])) {
            return;
        }

        $iterator = $DB->request([
            'SELECT' => ['name', 'rights'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => [
                'profiles_id' => (int) $_SESSION['glpiactiveprofile']['id'],
                'name'        => ['LIKE', 'plugin_paineldebordo%'],
            ],
        ]);

        foreach ($iterator as $prof) {
            if (isset($_SESSION['glpiactiveprofile'][$prof['name']])) {
                unset($_SESSION['glpiactiveprofile'][$prof['name']]);
            }
            $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
        }
    }

    public static function removeRightsFromSession()
    {
        foreach (self::getAllRights(true) as $right) {
            if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
                unset($_SESSION['glpiactiveprofile'][$right['field']]);
            }
        }
    }

    public static function uninstallProfile()
    {
        $rights = self::getAllRights(true);
        foreach ($rights as $right) {
            ProfileRight::deleteProfileRights([$right['field']]);
        }
        self::removeRightsFromSession();
    }
}
