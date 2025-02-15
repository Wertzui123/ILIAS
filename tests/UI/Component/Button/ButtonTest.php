<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

require_once(__DIR__ . "/../../../../libs/composer/vendor/autoload.php");
require_once(__DIR__ . "/../../Base.php");

use ILIAS\UI\Component as C;
use ILIAS\UI\Implementation\Component\Signal;
use ILIAS\UI\Implementation\Component\Button\Factory;
use ILIAS\UI\Help;

/**
 * Test on button implementation.
 */
class ButtonTest extends ILIAS_UI_TestBase
{
    public const NOT_APPLICABLE = true;

    public function getButtonFactory(): Factory
    {
        return new Factory();
    }

    public static array $canonical_css_classes = [
        "standard" => "btn btn-default",
        "primary" => "btn btn-default btn-primary",
        "shy" => "btn btn-link",
        "tag" => "btn btn-tag btn-tag-relevance-veryhigh"
    ];

    public function testImplementsFactoryInterface(): void
    {
        $f = $this->getButtonFactory();

        $this->assertInstanceOf("ILIAS\\UI\\Component\\Button\\Factory", $f);
        $this->assertInstanceOf(
            "ILIAS\\UI\\Component\\Button\\Standard",
            $f->standard("label", "http://www.ilias.de")
        );
        $this->assertInstanceOf(
            "ILIAS\\UI\\Component\\Button\\Primary",
            $f->primary("label", "http://www.ilias.de")
        );
        $this->assertInstanceOf(
            "ILIAS\\UI\\Component\\Button\\Close",
            $f->close()
        );
        $this->assertInstanceOf(
            "ILIAS\\UI\\Component\\Button\\Shy",
            $f->shy("label", "http://www.ilias.de")
        );
    }

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testButtonLabelOrGlyphOnly(string $factory_method): void
    {
        $this->expectException(TypeError::class);
        $f = $this->getButtonFactory();
        $f->$factory_method($this, "http://www.ilias.de");
    }

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testButtonStringActionOnly(string $factory_method): void
    {
        $this->expectException(InvalidArgumentException::class);
        $f = $this->getButtonFactory();
        $f->$factory_method("label", $this);
    }

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testButtonLabel(string $factory_method): void
    {
        $f = $this->getButtonFactory();
        $b = $f->$factory_method("label", "http://www.ilias.de");

        $this->assertEquals("label", $b->getLabel());
    }

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testButtonWithLabel(string $factory_method): void
    {
        $f = $this->getButtonFactory();
        $b = $f->$factory_method("label", "http://www.ilias.de");

        $b2 = $b->withLabel("label2");

        $this->assertEquals("label", $b->getLabel());
        $this->assertEquals("label2", $b2->getLabel());
    }

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testButtonAction(string $factory_method): void
    {
        $f = $this->getButtonFactory();
        $b = $f->$factory_method("label", "http://www.ilias.de");

        $this->assertEquals("http://www.ilias.de", $b->getAction());
    }

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testButtonActivatedOnDefault(string $factory_method): void
    {
        $f = $this->getButtonFactory();
        $b = $f->$factory_method("label", "http://www.ilias.de");

        $this->assertTrue($b->isActive());
    }

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testButtonDeactivation(string $factory_method): void
    {
        $f = $this->getButtonFactory();
        $b = $f->$factory_method("label", "http://www.ilias.de")
               ->withUnavailableAction();

        $this->assertFalse($b->isActive());
        $this->assertEquals("http://www.ilias.de", $b->getAction());

        $b = $b->withUnavailableAction(false);
        $this->assertTrue($b->isActive());
    }

    /**
     * test loading animation
     */
    public function testButtonWithLoadingAnimation(): void
    {
        $f = $this->getButtonFactory();
        foreach (["standard", "primary"] as $method) {
            $b = $f->$method("label", "http://www.ilias.de");

            $this->assertFalse($b->hasLoadingAnimationOnClick());

            $b = $b->withLoadingAnimationOnClick(true);

            $this->assertTrue($b->hasLoadingAnimationOnClick());
        }
    }

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testRenderButtonLabel(string $factory_method): void
    {
        $ln = "http://www.ilias.de";
        $f = $this->getButtonFactory();
        $b = $f->$factory_method("label", $ln);
        $r = $this->getDefaultRenderer();

        $html = $this->normalizeHTML($r->render($b));

        $css_classes = self::$canonical_css_classes[$factory_method];
        $expected = "<button class=\"$css_classes\" data-action=\"$ln\" id=\"id_1\">" .
            "label" .
            "</button>";
        $this->assertHTMLEquals($expected, $html);
    }

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testRenderButtonDisabled(string $factory_method): void
    {
        $ln = "http://www.ilias.de";
        $f = $this->getButtonFactory();
        $b = $f->$factory_method("label", $ln)
               ->withUnavailableAction();
        $r = $this->getDefaultRenderer();

        $html = $this->normalizeHTML($r->render($b));

        $css_classes = self::$canonical_css_classes[$factory_method];
        $expected = "<button class=\"$css_classes\" data-action=\"$ln\" disabled=\"disabled\">" .
            "label" .
            "</button>";
        $this->assertHTMLEquals($expected, $html);
    }

    public function testRenderCloseButton(): void
    {
        $f = $this->getButtonFactory();
        $r = $this->getDefaultRenderer();
        $b = $f->close();

        $html = $this->normalizeHTML($r->render($b));

        $expected = "<button type=\"button\" class=\"close\" aria-label=\"close\">" .
            "	<span aria-hidden=\"true\">&times;</span>" .
            "</button>";
        $this->assertEquals($expected, $html);
    }

    public function testRenderMinimizeButton(): void
    {
        $f = $this->getButtonFactory();
        $r = $this->getDefaultRenderer();
        $b = $f->minimize();

        $html = $this->normalizeHTML($r->render($b));

        $expected = "<button type=\"button\" class=\"minimize\" aria-label=\"minimize\">" .
            "	<span aria-hidden=\"true\">−</span>" .
            "</button>";
        $this->assertEquals($expected, $html);
    }

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testRenderButtonWithOnLoadCode(string $factory_method): void
    {
        $ln = "http://www.ilias.de";
        $f = $this->getButtonFactory();
        $r = $this->getDefaultRenderer();
        $ids = array();
        $b = $f->$factory_method("label", $ln)
               ->withOnLoadCode(function ($id) use (&$ids): string {
                   $ids[] = $id;
                   return "";
               });

        $html = $this->normalizeHTML($r->render($b));

        $this->assertCount(1, $ids);

        $id = $ids[0];
        $css_classes = self::$canonical_css_classes[$factory_method];
        $expected = "<button class=\"$css_classes\" data-action=\"$ln\" id=\"$id\">" .
            "label" .
            "</button>";
        $this->assertHTMLEquals($expected, $html);
    }

    public function testRenderCloseButtonWithOnLoadCode(): void
    {
        $f = $this->getButtonFactory();
        $r = $this->getDefaultRenderer();
        $ids = array();
        $b = $f->close()
               ->withOnLoadCode(function ($id) use (&$ids): string {
                   $ids[] = $id;
                   return "";
               });

        $html = $this->normalizeHTML($r->render($b));

        $this->assertCount(1, $ids);

        $id = $ids[0];
        $expected = "<button type=\"button\" class=\"close\" aria-label=\"close\" id=\"$id\">" .
            "	<span aria-hidden=\"true\">&times;</span>" .
            "</button>";
        $this->assertEquals($expected, $html);
    }

    public function testBtnTagRelevance(): void
    {
        $f = $this->getButtonFactory();
        $b = $f->tag('tag', '#');

        $this->expectException(TypeError::class);
        $b->withRelevance(0);

        $this->expectException(TypeError::class);
        $b->withRelevance('notsoimportant');
    }

    public function testRenderBtnTagRelevance(): void
    {
        $expectations = array(
            '<button class="btn btn-tag btn-tag-relevance-verylow" data-action="#" id="id_1">tag</button>',
            '<button class="btn btn-tag btn-tag-relevance-low" data-action="#" id="id_2">tag</button>',
            '<button class="btn btn-tag btn-tag-relevance-middle" data-action="#" id="id_3">tag</button>',
            '<button class="btn btn-tag btn-tag-relevance-high" data-action="#" id="id_4">tag</button>',
            '<button class="btn btn-tag btn-tag-relevance-veryhigh" data-action="#" id="id_5">tag</button>'
        );

        $f = $this->getButtonFactory();
        $r = $this->getDefaultRenderer();
        $t = $f->tag('tag', '#');
        $possible_relevances = array(
            $t::REL_VERYLOW,
            $t::REL_LOW,
            $t::REL_MID,
            $t::REL_HIGH,
            $t::REL_VERYHIGH
        );
        foreach ($possible_relevances as $w) {
            $html = $this->normalizeHTML(
                $r->render($t->withRelevance($w))
            );
            $expected = $expectations[array_search($w, $possible_relevances)];
            $this->assertEquals($expected, $html);
        }
    }

    public function testRenderBtnTagColors(): void
    {
        $f = $this->getButtonFactory();
        $r = $this->getDefaultRenderer();
        $df = new \ILIAS\Data\Factory();

        $bgcol = $df->color('#00ff00');

        $b = $f->tag('tag', '#')
               ->withBackgroundColor($bgcol);
        $html = $this->normalizeHTML($r->render($b));
        $expected = '<button class="btn btn-tag btn-tag-relevance-veryhigh" style="background-color: #00ff00; color: #000000;" data-action="#" id="id_1">tag</button>';
        $this->assertEquals($expected, $html);

        $fcol = $df->color('#ddd');
        $b = $b->withForegroundColor($fcol);
        $html = $this->normalizeHTML($r->render($b));
        $expected = '<button class="btn btn-tag btn-tag-relevance-veryhigh" style="background-color: #00ff00; color: #dddddd;" data-action="#" id="id_2">tag</button>';
        $this->assertEquals($expected, $html);
    }

    public function testRenderBtnTagClasses(): void
    {
        $f = $this->getButtonFactory();
        $r = $this->getDefaultRenderer();
        $df = new \ILIAS\Data\Factory();

        $classes = array('cl1', 'cl2');
        $b = $f->tag('tag', '#')
               ->withClasses($classes);
        $this->assertEquals($classes, $b->getClasses());

        $html = $this->normalizeHTML($r->render($b));
        $expected = '<button class="btn btn-tag btn-tag-relevance-veryhigh cl1 cl2" data-action="#" id="id_1">tag</button>';
        $this->assertEquals($expected, $html);
    }

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testButtonWithAriaLabel(string $factory_method): void
    {
        $f = $this->getButtonFactory();
        $b = $f->$factory_method("label", "http://www.ilias.de")->withAriaLabel("ariatext");
        $this->assertEquals("ariatext", $b->getAriaLabel());
    }

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testButtonWithEngageable(string $factory_method): void
    {
        $f = $this->getButtonFactory();
        $b = $f->$factory_method("label", "http://www.ilias.de");
        if ($b instanceof C\Button\Engageable) {
            $this->assertEquals(false, $b->isEngageable());
            $b2 = $f->$factory_method("label", "http://www.ilias.de")->withEngagedState(false);
            $this->assertEquals(true, $b2->isEngageable());
        } else {
            $this->assertTrue(self::NOT_APPLICABLE);
        }
    }

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testButtonWithEngaged(string $factory_method): void
    {
        $f = $this->getButtonFactory();
        $b = $f->$factory_method("label", "http://www.ilias.de");
        if ($b instanceof C\Button\Engageable) {
            $b = $b->withEngagedState(false);
            $this->assertEquals(false, $b->isEngaged());
            $b2 = $f->$factory_method("label", "http://www.ilias.de")->withEngagedState(true);
            $this->assertEquals(true, $b2->isEngaged());
        } else {
            $this->assertTrue(self::NOT_APPLICABLE);
        }
    }

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testRenderButtonWithAriaLabel(string $factory_method): void
    {
        $ln = "http://www.ilias.de";
        $f = $this->getButtonFactory();
        $r = $this->getDefaultRenderer();
        $b = $f->$factory_method("label", $ln)->withAriaLabel("aria label text");
        $aria_label = $b->getAriaLabel();

        $html = $this->normalizeHTML($r->render($b));
        $css_classes = self::$canonical_css_classes[$factory_method];
        $expected = "<button class=\"$css_classes\" aria-label=\"$aria_label\" data-action=\"$ln\" id=\"id_1\">" .
            "label" .
            "</button>";
        $this->assertHTMLEquals($expected, $html);
    }

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testRenderButtonWithAriaPressed(string $factory_method): void
    {
        $ln = "http://www.ilias.de";
        $f = $this->getButtonFactory();
        $r = $this->getDefaultRenderer();
        $b = $f->$factory_method("label", $ln);
        if ($b instanceof C\Button\Engageable) {
            $b = $b->withEngagedState(true);

            $html = $this->normalizeHTML($r->render($b));
            $css_classes = self::$canonical_css_classes[$factory_method];
            $css_classes .= ' engaged';
            $expected = "<button class=\"$css_classes\" aria-pressed=\"true\" data-action=\"$ln\" id=\"id_1\">" .
                "label" .
                "</button>";
            $this->assertHTMLEquals($expected, $html);
        } else {
            $this->assertTrue(self::NOT_APPLICABLE);
        }
    }

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testWithOnClickRemovesAction(string $factory_method): void
    {
        $f = $this->getButtonFactory();
        $signal = $this->createMock(C\Signal::class);
        $button = $f->$factory_method("label", "http://www.example.com");
        $this->assertEquals("http://www.example.com", $button->getAction());

        $button = $button->withOnClick($signal);

        $this->assertEquals([$signal], $button->getAction());
    }

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testAppendOnClickAppendsToAction(string $factory_method): void
    {
        $f = $this->getButtonFactory();
        $signal1 = $this->createMock(C\Signal::class);
        $signal2 = $this->createMock(C\Signal::class);
        $button = $f->$factory_method("label", "http://www.example.com");

        $button = $button->withOnClick($signal1)->appendOnClick($signal2);

        $this->assertEquals([$signal1, $signal2], $button->getAction());
    }

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testRenderButtonWithSignal(string $factory_method): void
    {
        $ln = "http://www.ilias.de";
        $f = $this->getButtonFactory();
        $signal = $this->createMock(Signal::class);
        $signal->method("__toString")
               ->willReturn("MOCK_SIGNAL");

        $b = $f->$factory_method("label", $ln)
               ->withOnClick($signal);
        $r = $this->getDefaultRenderer();

        $html = $this->normalizeHTML($r->render($b));

        $css_classes = self::$canonical_css_classes[$factory_method];
        $expected = "<button class=\"$css_classes\" id=\"id_1\">" .
            "label" .
            "</button>";
        $this->assertHTMLEquals($expected, $html);
    }

    /**
     * test rendering with on click animation
     */
    public function testRenderButtonWithOnClickAnimation(): void
    {
        foreach (["primary", "standard"] as $method) {
            $ln = "http://www.ilias.de";
            $f = $this->getButtonFactory();
            $r = $this->getDefaultRenderer();
            $b = $f->$method("label", $ln)
                   ->withLoadingAnimationOnClick(true);

            $html = $this->normalizeHTML($r->render($b));

            $css_classes = self::$canonical_css_classes[$method];
            $expected = "<button class=\"$css_classes\" data-action=\"$ln\" id=\"id_1\">" .
                "label" .
                "</button>";
            $this->assertHTMLEquals($expected, $html);
        }
    }

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testButtonRendersTooltip(string $factory_method): void
    {
        $f = $this->getButtonFactory();
        $r = $this->getDefaultRenderer();
        $ln = "http://www.ilias.de";

        $button =
            $f->$factory_method("label", $ln)
               ->withHelpTopics(new Help\Topic("a"), new Help\Topic("b"));

        $css_classes = self::$canonical_css_classes[$factory_method];
        $expected =
            "<div class=\"c-tooltip__container\">" .
                 "<button class=\"$css_classes\" aria-describedby=\"id_2\" data-action=\"$ln\" id=\"id_1\" >" .
                    "label" .
                "</button>" .
                "<div id=\"id_2\" role=\"tooltip\" class=\"c-tooltip c-tooltip--hidden\">" .
                    "<p>tooltip: a</p>" .
                    "<p>tooltip: b</p>" .
                "</div>" .
            "</div>";

        $html = $this->normalizeHTML($r->render($button));
        $this->assertHTMLEquals($expected, $html);
    }


    // TODO: We are missing a test for the rendering of a button with an signal
    // here. Does it still render the action js?

    /**
     * @dataProvider getButtonTypeProvider
     */
    public function testFactoryAcceptsSignalAsAction(string $factory_method): void
    {
        $f = $this->getButtonFactory();
        $signal = $this->createMock(C\Signal::class);

        $button = $f->$factory_method("label", $signal);

        $this->assertEquals([$signal], $button->getAction());
    }

    public function getButtonTypeProvider(): array
    {
        return [
            ['standard'],
            ['primary'],
            ['shy'],
            ['tag']
        ];
    }
}
