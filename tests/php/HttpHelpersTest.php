<?php
use PHPUnit\Framework\TestCase;

// Tests for HTTP helper functions in src/lib.php.
final class HttpHelpersTest extends TestCase {
  public function test_parse_weighted_header_orders_by_q(): void {
    $items = parse_weighted_header('en-US,en;q=0.9,fr;q=0.8');
    $this->assertSame('en-US', $items[0]['token']);
    $this->assertSame('en', $items[1]['token']);
    $this->assertSame('fr', $items[2]['token']);
  }

  public function test_parse_weighted_header_defaults_q(): void {
    $items = parse_weighted_header('gzip, br;q=0.8');
    $this->assertSame('gzip', $items[0]['token']);
  }

  public function test_parse_accept_language_dedupes(): void {
    $langs = parse_accept_language('fr-FR, fr;q=0.9, fr-FR;q=0.8');
    $this->assertSame(['fr-FR', 'fr'], $langs);
  }

  public function test_parse_accept_encoding_handles_empty(): void {
    $enc = parse_accept_encoding(null);
    $this->assertSame([], $enc);
  }

  public function test_parse_user_agent_detects_browser_and_os(): void {
    $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15';
    $info = parse_user_agent($ua);
    $this->assertSame('Safari 17.1', $info['browser']);
    $this->assertSame('macOS 10.15.7', $info['os']);
  }

  public function test_reverse_ipv4(): void {
    $this->assertSame('1.0.168.192', reverse_ipv4('192.168.0.1'));
    $this->assertNull(reverse_ipv4('not-an-ip'));
  }

  public function test_tor_exit_dns_check_returns_null_with_invalid_input(): void {
    $this->assertNull(tor_exit_dns_check(null, null, 0));
  }
}
