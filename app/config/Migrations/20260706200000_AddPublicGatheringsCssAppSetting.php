<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Seed the "Plugin.PublicGatherings.CustomCSS" app setting (type css).
 *
 * The value is a documented, fully-commented template: it renders no visual
 * change by default (it is all CSS comments) but teaches the administrator the
 * theme variables and classes they can override to restyle the /events page.
 */
class AddPublicGatheringsCssAppSetting extends AbstractMigration
{
    private const SETTING_NAME = 'Plugin.PublicGatherings.CustomCSS';

    public function up(): void
    {
        $exists = $this->fetchRow(
            "SELECT id FROM app_settings WHERE name = '" . self::SETTING_NAME . "'",
        );
        if ($exists) {
            // Only ensure the type is css; never clobber an admin's edits.
            $this->execute(
                "UPDATE app_settings SET type = 'css' WHERE name = '" . self::SETTING_NAME . "'",
            );

            return;
        }

        $default = $this->defaultCss();
        $this->table('app_settings')->insert([
            [
                'name' => self::SETTING_NAME,
                'value' => $default,
                'type' => 'css',
                'required' => false,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
        ])->saveData();
    }

    public function down(): void
    {
        $this->execute(
            "DELETE FROM app_settings WHERE name = '" . self::SETTING_NAME . "'",
        );
    }

    /**
     * The documented default. Note: no literal comment-close sequence appears
     * inside the guide text, so the block comments stay well-formed.
     */
    private function defaultCss(): string
    {
        return <<<'CSS'
/* Public Kingdom Calendar — custom styles.
   Rules here are added to the /events page after the built-in styles, so
   yours win. The whole page is built from the CSS variables listed below;
   change a variable to reskin the entire calendar. To use the example at the
   bottom, delete the comment fences that wrap it, then edit the values.

   THEME VARIABLES (set these on .kingdom-calendar-page):
     --kc-bg               page background (parchment)
     --kc-surface          event card background
     --kc-ink              main text color
     --kc-muted            secondary text color
     --kc-accent           MAIN accent: date shields, links, subscribe button
     --kc-accent-contrast  text/marks drawn on top of the accent color
     --kc-gold             crowns, royal progress, and order circles
     --kc-rule             hairlines and borders
     --kc-radius           corner rounding of cards (e.g. 12px)
     --kc-maxw             max width of the calendar column (e.g. 880px)
     --kc-font-display     headings and event names
     --kc-font-body        body text

   USEFUL CLASSES (target these for finer control):
     .kingdom-calendar-page   the whole page wrapper
     .kc-eyebrow .kc-title .kc-subtitle   masthead text
     .kc-subscribe-link       the "Subscribe to Calendar" button
     .kc-month-header         the sticky month banner
     .kc-event                one event card
     .kc-event-date           the heraldic date shield (uses --kc-accent)
     .kc-event-name           the event title
     .kc-badge-type           the event-type badge
     .kc-activity-chip        an activity pill (.kc-activity-chip-circle = order circle)
     .kc-event-progress       the royal progress line (uses --kc-gold)
     .kc-footer               the footer
*/

/* EXAMPLE — a black-and-gold kingdom. Delete this comment's fences to use it.
.kingdom-calendar-page {
    --kc-bg: #f7f4ee;
    --kc-surface: #ffffff;
    --kc-accent: #17130f;
    --kc-gold: #b8860b;
}
*/
CSS;
    }
}
