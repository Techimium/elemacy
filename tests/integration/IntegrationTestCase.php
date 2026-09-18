<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Tests\Integration\Fixtures\LibraryDocument;
use PHPUnit\Framework\TestCase;

/** Shared native fixtures; no WordPress globals or Elementor APIs are mocked. */
abstract class IntegrationTestCase extends TestCase
{
    protected LibraryDocument $documents;
    private int $previous_user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previous_user = get_current_user_id();
        $this->documents = new LibraryDocument();
        $administrator = get_user_by('login', 'elemacy_test_admin');
        self::assertInstanceOf(\WP_User::class, $administrator, 'Missing isolated administrator.');
        wp_set_current_user($administrator->ID);
    }

    protected function tearDown(): void
    {
        try {
            $this->documents->cleanup();
        } finally {
            wp_set_current_user($this->previous_user);
            parent::tearDown();
        }
    }
}
