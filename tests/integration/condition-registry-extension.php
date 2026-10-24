<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Conditions\ConditionEvaluator;
use Elemacy\Conditions\ConditionManager;
use Elemacy\Conditions\DTO\ConditionRuleDTO;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * Task 10.1/design.md D10: registries expose supported values/availability
 * to consumers, and a test extension can add a supported feature without
 * editing any central consumer. fixtures/mu-plugins/condition-registry-
 * extension.php registers `elemacy_fixture/registered_condition` purely
 * through the public Hooks::CONDITIONS_REGISTER_ACTION hook — the exact
 * mechanism elemacy-pro's real ProConditions.php uses — with no change to
 * ConditionManager, ConditionEvaluator or ConditionsBootstrap.
 *
 * Also verifies RegistryIdentifierGuard (new in this task): a reserved
 * identifier and a genuinely conflicting duplicate registration are both
 * refused, while the fixture's own real registration above was accepted.
 */
final class ConditionRegistryExtensionTest extends IntegrationTestCase
{
    public function testExtensionRegisteredConditionIsAvailableWithNoCentralFileEdited(): void
    {
        $availability = ConditionManager::instance()->get_availability('elemacy_fixture/registered_condition');

        self::assertTrue($availability->is_available(), 'The mu-plugin fixture must have registered this condition through the public hook alone.');
    }

    public function testExtensionRegisteredConditionParticipatesInEvaluationLikeAnyBuiltInCondition(): void
    {
        $matches = ConditionEvaluator::instance()->evaluate([
            ConditionRuleDTO::from_array(['type' => 'elemacy_fixture/registered_condition', 'operator' => 'include']),
        ]);

        self::assertTrue($matches, 'ConditionEvaluator must run the extension condition exactly like a built-in one.');
    }

    public function testRegisteringTheReservedIdentifierTypeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ConditionManager())->register(new class extends \Elemacy\Conditions\BaseCondition {
            public function get_name(): string
            {
                return 'type';
            }

            public function get_type(): string
            {
                return 'general';
            }

            public function get_label(): string
            {
                return 'Reserved';
            }

            public function check(ConditionRuleDTO $rule): bool
            {
                return true;
            }
        });
    }

    public function testTwoDistinctImplementationsCannotSilentlyClaimTheSameConditionName(): void
    {
        $registry = new ConditionManager();
        $registry->register(new class extends \Elemacy\Conditions\BaseCondition {
            public function get_name(): string
            {
                return 'elemacy_fixture/collision_condition';
            }

            public function get_type(): string
            {
                return 'general';
            }

            public function get_label(): string
            {
                return 'First';
            }

            public function check(ConditionRuleDTO $rule): bool
            {
                return true;
            }
        });

        $this->expectException(\InvalidArgumentException::class);

        $registry->register(new class extends \Elemacy\Conditions\BaseCondition {
            public function get_name(): string
            {
                return 'elemacy_fixture/collision_condition';
            }

            public function get_type(): string
            {
                return 'general';
            }

            public function get_label(): string
            {
                return 'Second, unrelated implementation';
            }

            public function check(ConditionRuleDTO $rule): bool
            {
                return false;
            }
        });
    }
}
