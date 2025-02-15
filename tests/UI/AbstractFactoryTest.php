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

require_once("libs/composer/vendor/autoload.php");

use ILIAS\UI\Implementation\Crawler\Exception as CrawlerException;
use ILIAS\UI\Implementation\Crawler as Crawler;
use PHPUnit\Framework\TestCase;

/**
 * Defines tests every SHOULD pass UI-factory. Checks as much rules as possible from
 * the 'Interfaces to Factories' part of the UI framework rules.
 *
 * TODO: This test heavily relies on data providers and dependencies. PHPUnit
 * does not support dependencies per provided data set via @depends, therefore
 * the test express the dependencies explicitly by calling subsequent testing
 * methods. These leads to test methods being executed to often.
 */
abstract class AbstractFactoryTest extends TestCase
{
    public const COMPONENT = 1;
    public const FACTORY = 2;

    /* This allows to omit checking of certain factory methods, use prudently...
     */
    private array $omit_factory_methods = [
        "helpTopics"
    ];

    /* kitchensink info test configuration:
     * true = should be there, check
     * false = may be there, don't check
     * Notice, some properties (MUST/MUST NOT) will always be checked.
     */
    private array $kitchensink_info_settings_default = [
        'description' => true,
        'background' => false,
        'context' => true,
        'featurewiki' => false,
        'javascript' => false,
        'rules' => true
    ];

    /* You can overwrite these settings per factory method when using this test
     * by writing $kitchensink_info_settings. See GlyphFactoryTest for an example.
     */


    // Definitions and Helpers:

    private array $description_categories = ['purpose', 'composition', 'effect', 'rival'];

    private array $rules_categories = [
        'usage',
        'interaction',
        'wording',
        'style',
        'ordering',
        'responsiveness',
        'composition',
        'accessibility'
    ];

    private Crawler\EntriesYamlParser $yaml_parser;
    private ReflectionClass $reflection;

    final protected function returnsFactory(array $docstring_data): bool
    {
        return $this->isFactoryName($docstring_data["namespace"]);
    }

    final protected function returnsComponent(array $docstring_data): bool
    {
        $reflection = new ReflectionClass($docstring_data["namespace"]);
        return in_array("ILIAS\\UI\\Component\\Component", $reflection->getInterfaceNames());
    }

    final protected function isFactoryName(string $name): bool
    {
        return preg_match("#^(\\\\)?ILIAS\\\\UI\\\\Component\\\\([a-zA-Z]+\\\\)*Factory$#", $name) === 1;
    }

    final public function buildFactoryReflection(): ReflectionClass
    {
        return new ReflectionClass($this->factory_title);
    }

    final public function getMethodsProvider(): array
    {
        $reflection = $this->buildFactoryReflection();
        return array_filter(
            array_map(function ($element) {
                if (!in_array($element->getName(), $this->omit_factory_methods)) {
                    return array($element, $element->getName());
                }
                return false;
            }, $reflection->getMethods())
        );
    }

    // Setup

    public function setUp(): void
    {
        $this->yaml_parser = new Crawler\EntriesYamlParser();
        $this->reflection = $this->buildFactoryReflection();
    }

    public function testProperNamespace(): void
    {
        $message = "TODO: Put your factory into the proper namespace.";
        $this->assertMatchesRegularExpression(
            "#^ILIAS\\\\UI\\\\Component.#",
            $this->reflection->getNamespaceName(),
            $message
        );
    }

    public function testProperName(): void
    {
        $name = $this->reflection->getName();
        $message = "TODO: Give your factory a proper name.";
        $this->assertTrue($this->isFactoryName($name), $message);
    }

    /**
     * Tests whether the YAML Kitchen Sink info can be parsed.
     *
     * @dataProvider getMethodsProvider
     */
    final public function testCheckYamlExtraction(ReflectionMethod $method_reflection, string $name): array
    {
        try {
            //Todo (TA) this is not pretty. We should think about using only reflection in the parser as well.
            $function_name_string = "\n public function " . $method_reflection->getName() . "()";
            $docstring_data = $this->yaml_parser->parseArrayFromString(
                $method_reflection->getDocComment() . $function_name_string
            );
            $this->assertTrue(true);
        } catch (CrawlerException\CrawlerException $e) {
            $message = "TODO ($name): fix parse error in kitchen sink yaml: " . $e->getMessage();
            $this->assertTrue(false, $message);
        }
        $this->assertCount(1, $docstring_data);
        return $docstring_data[0];
    }

    /**
     * Tests whether the method either returns a factory or a component.
     *
     * @dataProvider getMethodsProvider
     */
    final public function testReturnType(ReflectionMethod $method_reflection, string $name): void
    {
        $message = "TODO ($name): fix return type, it must be a factory or a component.";
        $docstring_data = $this->testCheckYamlExtraction($method_reflection, $name);
        if ($this->returnsFactory($docstring_data)) {
            $this->assertTrue(true);
        } elseif ($this->returnsComponent($docstring_data)) {
            $this->assertTrue(true);
        } else {
            $this->assertTrue(false, $message);
        }
    }

    /**
     * Tests whether the method name matches the return doctring?
     *
     * @dataProvider getMethodsProvider
     */
    final public function testFactoryMethodNameCompatibleDocstring(
        ReflectionMethod $method_reflection,
        string $name
    ): void {
        $docstring_data = $this->testCheckYamlExtraction($method_reflection, $name);
        $this->testReturnType($method_reflection, $name);

        $return_doc = $docstring_data["namespace"];
        $name_uppercase = ucwords($name);
        $regex_factory_namespace = $this->getRegexFactoryNamespace();
        $regex_head = "#^(\\\\?)$regex_factory_namespace";

        $message = "TODO ($name): fix @return, it does not match the method name.";
        if ($this->returnsFactory($docstring_data)) {
            $this->assertMatchesRegularExpression(
                "$regex_head\\\\$name_uppercase\\\\Factory$#",
                $return_doc,
                $message
            );
        } else { // returnsComponent
            // Every component MUST be described by a single interface, where the name of
            // the interface corresponds to the name of the component.
            $standard_pattern = "$regex_head\\\\$name_uppercase#";
            $standard_case = preg_match($standard_pattern, $return_doc);

            // unless they only differ in a type and share a common prefix to their pathes.
            $namespace_parts = explode("\\", $this->reflection->getNamespaceName());
            $typediff_only_pattern = "$regex_head\\\\" . array_pop($namespace_parts) . "#";
            $typediff_only_case = preg_match($typediff_only_pattern, $return_doc);

            $this->assertTrue($standard_case || $typediff_only_case, $message);
        }
    }

    protected function getRegexFactoryNamespace(): string
    {
        return str_replace("\\", "\\\\", $this->reflection->getNamespaceName());
    }

    /**
     * Tests whether methods returning factories have no parameters.
     *
     * @dataProvider getMethodsProvider
     */
    final public function testMethodParams(ReflectionMethod $method_reflection, string $name): void
    {
        $docstring_data = $this->testCheckYamlExtraction($method_reflection, $name);
        if ($this->returnsFactory($docstring_data)) {
            $message = "TODO ($name): remove params from method that returns Factory.";
            $this->assertEquals(0, $method_reflection->getNumberOfParameters(), $message);
        }
    }

    // Common rules for all factory methods, regardless whether they return other
    // factories or components.

    /**
     * @dataProvider getMethodsProvider
     */
    final public function testKitchensinkInfoDescription(ReflectionMethod $method_reflection, string $name): void
    {
        $docstring_data = $this->testCheckYamlExtraction($method_reflection, $name);
        $kitchensink_info_settings = $this->kitchensinkInfoSettingsMergedWithDefaults($name);

        if ($kitchensink_info_settings['description']) {
            $message = "TODO ($name): add a description.";
            $this->assertArrayHasKey('description', $docstring_data, $message);

            $desc_fields = implode(", ", $this->description_categories);
            $message = "TODO ($name): the description field should at least contain one of these: $desc_fields.";
            $existing_keys = array_keys($docstring_data["description"]);
            $existing_expected_keys = array_intersect($this->description_categories, $existing_keys);
            $this->assertGreaterThanOrEqual(
                1,
                $existing_expected_keys,
                $message
            );
        }
    }

    /**
     * @dataProvider getMethodsProvider
     */
    final public function testKitchensinkInfoRivals(ReflectionMethod $method_reflection, string $name): void
    {
        $docstring_data = $this->testCheckYamlExtraction($method_reflection, $name);
        if (isset($docstring_data["description"]) && isset($docstring_data["description"]["rivals"])) {
            $rules = $docstring_data["description"]["rivals"];
            $message = "TODO ($name): The Rivals field has a non-string index. Format like 'rival_name': 'description'";
            $this->assertTrue(array_unique(array_map("is_string", array_keys($rules))) === array(true), $message);
        }
        $this->assertTrue(true);
    }

    /**
     * @dataProvider getMethodsProvider
     */
    final public function testKitchensinkInfoBackground(ReflectionMethod $method_reflection, string $name): void
    {
        $docstring_data = $this->testCheckYamlExtraction($method_reflection, $name);
        $kitchensink_info_settings = $this->kitchensinkInfoSettingsMergedWithDefaults($name);

        if ($kitchensink_info_settings['background']) {
            $message = "TODO ($name): add a background field.";
            $this->assertArrayHasKey('background', $docstring_data, $message);
        }
    }

    /**
     * @dataProvider getMethodsProvider
     */
    final public function testKitchensinkInfoFeatureWiki(ReflectionMethod $method_reflection, string $name): void
    {
        $docstring_data = $this->testCheckYamlExtraction($method_reflection, $name);
        $kitchensink_info_settings = $this->kitchensinkInfoSettingsMergedWithDefaults($name);

        if ($kitchensink_info_settings['featurewiki']) {
            $message = "TODO ($name): add a featurewiki field.";
            $this->assertArrayHasKey('featurewiki', $docstring_data, $message);
        }
    }

    /**
     * @dataProvider getMethodsProvider
     */
    final public function testKitchensinkInfoJavaScript(ReflectionMethod $method_reflection, string $name): void
    {
        $docstring_data = $this->testCheckYamlExtraction($method_reflection, $name);
        $kitchensink_info_settings = $this->kitchensinkInfoSettingsMergedWithDefaults($name);

        if ($kitchensink_info_settings['javascript']) {
            $message = "TODO ($name): add a javascript field.";
            $this->assertArrayHasKey('javascript', $docstring_data, $message);
        }
    }

    /**
     * @dataProvider getMethodsProvider
     */
    final public function testKitchensinkInfoRules(ReflectionMethod $method_reflection, string $name): void
    {
        $docstring_data = $this->testCheckYamlExtraction($method_reflection, $name);
        $kitchensink_info_settings = $this->kitchensinkInfoSettingsMergedWithDefaults($name);

        if ($kitchensink_info_settings['rules']) {
            $message = "TODO ($name): add a rules field.";
            $this->assertArrayHasKey('rules', $docstring_data, $message);

            $rules_fields = implode(", ", $this->rules_categories);
            $message = "TODO ($name): the rules field should at least contain one of these: $rules_fields.";
            $existing_keys = array_keys($docstring_data["rules"]);
            $existing_expected_keys = array_intersect($this->rules_categories, $existing_keys);
            $this->assertGreaterThanOrEqual(
                1,
                $existing_expected_keys,
                $message
            );
        }
    }

    /**
     * @dataProvider getMethodsProvider
     */
    final public function testKitchensinkInfoContext(ReflectionMethod $method_reflection, string $name): void
    {
        $docstring_data = $this->testCheckYamlExtraction($method_reflection, $name);
        $kitchensink_info_settings = $this->kitchensinkInfoSettingsMergedWithDefaults($name);
        if (!$this->returnsFactory($docstring_data) && $kitchensink_info_settings["context"]) {
            $message = "TODO ($name): factory method returning component should have context field. Add it.";
            $this->assertArrayHasKey("context", $docstring_data, $message);
        }
    }

    final public function kitchensinkInfoSettingsMergedWithDefaults(string $name): array
    {
        if (array_key_exists($name, $this->kitchensink_info_settings)) {
            return array_merge(
                $this->kitchensink_info_settings_default,
                $this->kitchensink_info_settings[$name]
            );
        } else {
            return $this->kitchensink_info_settings_default;
        }
    }
}
