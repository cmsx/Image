<?php

require_once __DIR__ . '/../init.php';

use CMSx\Image;

class ImageTest extends PHPUnit_Framework_TestCase
{
  protected $image;
  protected $bike;
  protected $watermark;
  protected $path_to_save;

  function setUp()
  {
    $this->image        = realpath(__DIR__ . '/../resourse/1000x500.gif');
    $this->bike         = realpath(__DIR__ . '/../resourse/bike.jpg');
    $this->watermark    = realpath(__DIR__ . '/../resourse/magic.png');
    $this->path_to_save = realpath(__DIR__ . '/../tmp');
  }

  /**
   * @dataProvider resize_dimensions
   */
  function testCalculateDimensions($exp, $w, $h, $o_w, $o_h)
  {
    list ($x, $y) = Image::CalculateResize($w, $h, $o_w, $o_h);
    $this->assertEquals($exp, $x . 'x' . $y, 'Resize ' . $o_w . 'x' . $o_h . ' to ' . $w . 'x' . $h);
  }

  /**
   * @dataProvider offset_dimensions
   */
  function testCalculateOffset($exp, $x, $y)
  {
    $img_w  = 1000;
    $img_h  = 500;
    $item_w = 100;
    $item_h = 30;
    list ($new_x, $new_y) = Image::CalculateOffset($x, $y, $img_w, $img_h, $item_w, $item_h);
    $this->assertEquals($exp, $new_x . 'x' . $new_y, 'Offset ' . $x . ' ' . $y);
  }

  function testResizing()
  {
    $img = new Image($this->image);
    $img
      ->addResize(400, 300)
      ->save($this->path_to_save . '/test_resized.png');

    $new = Image::Me($this->path_to_save . '/test_resized.png'); //Another type of init
    $this->assertEquals(400, $new->getWidth(), 'Resized image has correct width');
    $this->assertEquals(200, $new->getHeight(), 'Resized image has correct height');
    $this->assertTrue($new->getType(IMG_PNG), 'Resized image has type PNG');

    unlink($this->path_to_save . '/test_resized.png');
  }

  function testCrop()
  {
    Image::Me($this->image)
      ->addResize(400, 300)
      ->addCrop(300, 300, 'center')
      ->save($this->path_to_save . '/test_croped.png');

    $new = Image::Me($this->path_to_save . '/test_croped.png');
    $this->assertEquals(300, $new->getWidth(), 'Croped image has correct width');
    $this->assertEquals(200, $new->getHeight(), 'Croped image has correct height');
    unlink($this->path_to_save . '/test_croped.png');
  }

  function testCroppingCorners()
  {
    Image::Me($this->image)
      ->addResize(400, 300)
      ->addCrop(150, 150, 'left', 'top')
      ->save($this->path_to_save . '/test_croped_left_top.png');

    Image::Me($this->image)
      ->addResize(400, 300)
      ->addCrop(150, 150, 'center', 'center')
      ->save($this->path_to_save . '/test_croped_center_center.jpg');

    Image::Me($this->image)
      ->addResize(400, 300)
      ->addCrop(150, 150, 'right', 'bottom')
      ->save($this->path_to_save . '/test_croped_right_bottom.gif');

    $this->markTestIncomplete('Необходимо проверять правильность обрезки вручную!');
  }

  /** Необходимо проверять расположение водяного знака вручную! */
  function testWatermark()
  {
    Image::Me($this->bike)
      ->addWatermark($this->watermark)
      ->save($this->path_to_save . '/test_watermark.jpg');

    Image::Me($this->bike)
      ->addWatermark($this->watermark, '30%', '30%')
      ->save($this->path_to_save . '/test_watermark_thirds.jpg');

    Image::Me($this->bike)
      ->addWatermark($this->watermark, -5, -5)
      ->save($this->path_to_save . '/test_watermark_minus.jpg');

    Image::Me($this->bike)
      ->addResize(400, 300)
      ->addCrop(300, 300)
      ->addWatermark($this->watermark)
      ->save($this->path_to_save . '/test_watermark_modifiers.jpg');
  }

  function resize_dimensions()
  {
    return array(
      array('400x300', 400, 300, 1200, 900),
      array('266x200', 400, 200, 1200, 900),
      array('400x300', 400, 500, 1200, 900),
      array('600x450', 1600, 450, 1200, 900),
      array('400x300', 400, 400, 1200, 900),
      array('399x265', 400, 300, 800, 531),
    );
  }

  function offset_dimensions()
  {
    return array(
      array('450x235', 'center', 'center'),
      array('900x470', 'right', 'bottom'),
      array('0x0', 'left', 'top'),
      array('250x135', '30%', '30%'),
      array('0x0', '5%', '2%'),
      array('900x470', '95%', '98%'),
      array('900x470', '100%', '100%'),
      array('0x0', '0%', '0%'),
      array('300x150', 300, 150),
      array('890x460', -10, -10)
    );
  }
}