<?php

namespace Drupal\Tests\uc_cart\Functional;

use Drupal\Tests\uc_store\Functional\UbercartBrowserTestBase;

/**
 * Tests the cart block functionality.
 *
 * @group ubercart
 */
class CartBlockTest extends UbercartBrowserTestBase {

  public static $modules = ['uc_cart', 'block'];

  /**
   * The cart block being tested.
   *
   * @var \Drupal\block\Entity\Block
   */
  protected $block;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->block = $this->drupalPlaceBlock('uc_cart_block');
  }

  /**
   * Test cart block functionality.
   */
  public function testCartBlock() {
    // Test the empty cart block.
    $this->drupalGet('');

    $this->assertRaw('empty');
    $this->assertText('There are no products in your shopping cart.');
    $this->assertText('0 Items');
    $this->assertText('Total: $0.00');
    $this->assertNoLink('View cart');
    $this->assertNoLink('Checkout');

    // Test the cart block with an item.
    $this->addToCart($this->product);
    $this->drupalGet('');

    $this->assertNoRaw('empty');
    $this->assertNoText('There are no products in your shopping cart.');
    $this->assertText('1 ×');
    $this->assertText($this->product->label());
    $this->assertNoUniqueText(uc_currency_format($this->product->price->value));
    $this->assertText('1 Item');
    $this->assertText('Total: ' . uc_currency_format($this->product->price->value));
    $this->assertLink('View cart');
    $this->assertLink('Checkout');
  }

  /**
   * Test hide cart when empty functionality.
   */
  public function testHiddenCartBlock() {
    $this->block->getPlugin()->setConfigurationValue('hide_empty', TRUE);
    $this->block->save();

    // Test the empty cart block.
    $this->drupalGet('');
    $this->assertNoText($this->block->label());

    // Test the cart block with an item.
    $this->addToCart($this->product);
    $this->drupalGet('');
    $this->assertText($this->block->label());
  }

  /**
   * Test show cart icon functionality.
   */
  public function testCartIcon() {
    $this->drupalGet('');
    $this->assertRaw('cart-block-icon');

    $this->block->getPlugin()->setConfigurationValue('show_image', FALSE);
    $this->block->save();

    $this->drupalGet('');
    $this->assertNoRaw('cart-block-icon');
  }

  /**
   * Test cart block collapse functionality.
   */
  public function testCartCollapse() {
    $this->drupalGet('');
    $this->assertRaw('cart-block-arrow');
    $this->assertRaw('collapsed');

    $this->block->getPlugin()->setConfigurationValue('collapsed', FALSE);
    $this->block->save();

    $this->drupalGet('');
    $this->assertNoRaw('collapsed');

    $this->block->getPlugin()->setConfigurationValue('collapsible', FALSE);
    $this->block->save();

    $this->drupalGet('');
    $this->assertNoRaw('cart-block-arrow');
  }

}
