<?php
/**
 * tests/bootstrap-unit.php
 *
 * Bootstrap for the FAST unit test suite (Brain Monkey mocked WP functions,
 * mocked EventRepository) — deliberately does NOT boot WordPress or touch
 * a database. If a unit test ever needs a real WP function that isn't
 * stubbed, it should fail loudly here rather than silently falling through
 * to a real WordPress environment.
 */
 
require_once __DIR__ . '/../vendor/autoload.php';