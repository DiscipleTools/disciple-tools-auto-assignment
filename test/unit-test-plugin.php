<?php

class PluginTest extends TestCase
{
    public function test_plugin_installed() {
        activate_plugin( 'disciple-tools-auto-assignment/disciple-tools-auto-assignment.php' );

        $this->assertContains(
            'disciple-tools-auto-assignment/disciple-tools-auto-assignment.php',
            get_option( 'active_plugins' )
        );
    }
}
