<?php

namespace CMSx;

class Image
{
  protected $source_file;
  protected $modify_arr;
  protected $width;
  protected $height;
  protected $type;
  protected $image;

  const RESIZE    = 1;
  const CROP      = 2;
  const WATERMARK = 3;

  const QUALITY_JPG = 75;
  const QUALITY_PNG = 3;

  /**
   * Возвращает новый объект Image.
   * $img - путь к файлу или GD-ресурс
   */
  public static function Me($img = null)
  {
    return new static($img);
  }

  /** $img - путь к файлу или GD-ресурс */
  function __construct($img = null)
  {
    $this->image  = static::Load($img, $this->type);
    $this->width  = imagesx($this->image);
    $this->height = imagesy($this->image);
  }

  /** Ширина оригинального изображения */
  public function getWidth()
  {
    return $this->width;
  }

  /** Высота оригинального изображения */
  public function getHeight()
  {
    return $this->height;
  }

  /**
   * Получение типа изображения: IMG_JPG | IMG_JPEG | IMG_PNG | IMG_GIF
   * $type - сравнение с указанным типом
   */
  public function getType($type = null)
  {

    return is_null($type) ? $this->type : ($this->type == $type);
  }

  /** GD-ресурс */
  public function getImage()
  {
    return $this->image;
  }

  /**
   * Пропорциональное изменение размера - добавляется в цепочку модификаторов.
   * Можно чередовать с CROP и WATERMARK.
   */
  public function addResize($width, $height)
  {
    $this->modify_arr[] = array(
      'method' => static::RESIZE,
      'width'  => $width,
      'height' => $height
    );

    return $this;
  }

  /**
   * Обрезка изображения - добавляется в цепочку модификаторов.
   * Можно чередовать с RESIZE и WATERMARK
   * $width - высота
   * $height - ширина
   * $x - отступ по горизонтали: left|right|center|±int - в пикселях|"int%" - в процентах
   * $y - отступ по вертикали: top|bottom|center|±int - в пикселях|"int%" - в процентах
   */
  public function addCrop($width, $height, $x = null, $y = null)
  {
    $this->modify_arr[] = array(
      'method' => static::CROP,
      'width'  => $width,
      'height' => $height,
      'x'      => $x,
      'y'      => $y
    );

    return $this;
  }

  /**
   * Водяной знак - добавляется в цепочку модификаторов.
   * Можно чередовать с RESIZE и CROP
   * $file - путь к изображению
   * $x - отступ по горизонтали: left|right|center|±int - в пикселях|"int%" - в процентах
   * $y - отступ по вертикали: top|bottom|center|±int - в пикселях|"int%" - в процентах
   */
  public function addWatermark($file, $x = null, $y = null)
  {
    $this->modify_arr[] = array(
      'method' => static::WATERMARK,
      'file'   => $file,
      'x'      => $x,
      'y'      => $y
    );

    return $this;
  }

  /** Вывод изображения в STDOUT */
  public function show($type = null)
  {
    $this->applyModifiers();

    return self::Make($this->image, null, $type);
  }

  /**
   * Сохранение в файл
   * Если $file не указан, будет перезаписан исходный файл
   * Если $type не указан, тип будет определен по расширению выходного файла
   * $type - IMG_JPG | IMG_JPEG | IMG_PNG | IMG_GIF
   */
  public function save($file = null, $type = null)
  {
    if (is_null($file)) {
      $file = $this->source_file;
    }
    if (is_null($file)) {
      throw new \Exception('Не задано имя выходного файла для изображения');
    }

    $this->applyModifiers();

    return self::Make($this->image, $file, $type);
  }

  /** Загрузка в GD-ресурс файла или ресурса */
  public static function Load($image, &$type = null)
  {
    if (is_resource($image)) {
      $type = null;

      return $image;
    }

    if (is_string($image)) {
      if (is_null($type)) {
        $type = static::GetTypeByFileName($image);
      }

      if (!is_file($image)) {
        $type = null;

        return imagecreatefromstring($image);
      }

      switch ($type) {
        case IMG_JPEG:
          return imagecreatefromjpeg($image);
          break;
        case IMG_GIF:
          return imagecreatefromgif($image);
          break;
        case IMG_PNG:
          return imagecreatefrompng($image);
          break;
        default:
          $file = file_get_contents($image);

          return imagecreatefromstring($file);
      }
    }

    return false;
  }

  /**
   * Расчет пропорционального изменения размеров изображения.
   * Возвращает массив [width, height]
   */
  public static function CalculateResize($width, $height, $orig_width, $orig_height)
  {
    $ratio = $orig_width / $orig_height;

    //Если изначальные размеры меньше необходимых - ничего не меняем
    if ($width >= $orig_width && $height >= $orig_height) {
      return array($orig_width, $orig_height);
    }

    $new_width  = $width;
    $new_height = $height;

    if ($width < $orig_width) {
      $new_width  = $width;
      $new_height = floor($width / $ratio);
    }

    if ($new_height > $height) {
      $new_width  = floor($new_width * ($new_height / $height));
      $new_height = $height;
    }

    if ($new_height < $orig_height) {
      $new_width = floor($new_height * $ratio);
    }

    return array($new_width, $new_height);
  }

  /**
   * Расчет заданного отступа по размерам изображения.
   * Возвращает массив [x, y]
   *
   * * $x - отступ по горизонтали: left|right|center|±int - в пикселях|"int%" - в процентах
   *
   * * $y - отступ по вертикали: top|bottom|center|±int - в пикселях|"int%" - в процентах
   */
  public static function CalculateOffset($x, $y, $image_width, $image_height, $item_width = null, $item_height = null)
  {
    //WIDTH
    if ($x == 'left') {
      $x = 0;
    } elseif ($x == 'center') {
      $x = floor($image_width / 2 - $item_width / 2);
    } elseif ($x == 'right') {
      $x = $image_width - $item_width;
    } elseif (strpos($x, '%')) {
      $x = floor((($image_width / 100) * $x) - ($item_width / 2));
      if ($x < 0) {
        $x = 0;
      }
      if (($x + $item_width) > $image_width) {
        $x = $image_width - $item_width;
      }
    } elseif ($x < 0) {
      $x = $image_width - $item_width + $x;
    }

    //HEIGHT
    if ($y == 'top') {
      $y = 0;
    } elseif ($y == 'center' || $y == 'middle') {
      $y = floor($image_height / 2 - $item_height / 2);
    } elseif ($y == 'bottom') {
      $y = $image_height - $item_height;
    } elseif (strpos($y, '%')) {
      $y = floor((($image_height / 100) * $y) - ($item_height / 2));
      if ($y < 0) {
        $y = 0;
      }
      if (($y + $item_height) > $image_height) {
        $y = $image_height - $item_height;
      }
    } elseif ($y < 0) {
      $y = $image_height - $item_height + $y;
    }

    return array((int)$x, (int)$y);
  }

  /**
   * Изменение размеров изображения. Возвращает GD-ресурс
   * $image - GD-ресурс
   */
  public static function Resize($image, $new_width, $new_height, $orig_width = null, $orig_height = null)
  {
    // Размеры
    if (is_null($orig_width)) {
      $orig_width = imagesx($image);
    }
    if (is_null($orig_height)) {
      $orig_height = imagesy($image);
    }
    list ($x, $y) = static::CalculateResize($new_width, $new_height, $orig_width, $orig_height);

    // Прозрачность
    $new_image = imagecreatetruecolor($x, $y);
    imagealphablending($new_image, true);
    imagefill($new_image, 0, 0, imagecolorallocatealpha($new_image, 0, 0, 0, 127));
    imagesavealpha($new_image, true);

    // Меняем размер
    if (!imagecopyresampled($new_image, $image, 0, 0, 0, 0, $x, $y, $orig_width, $orig_height)) {
      return false;
    }

    return $new_image;
  }

  /**
   * Обрезка изображения. Возвращает GD-ресурс
   * $image - GD-ресурс
   */
  public static function Crop($image, $width, $height, $x, $y, $image_w = null, $image_h = null)
  {
    // Размеры
    if (is_null($image_w)) {
      $image_w = imagesx($image);
    }
    if (is_null($image_h)) {
      $image_h = imagesy($image);
    }

    if ($width > $image_w) {
      $width = $image_w;
    }
    if ($height > $image_h) {
      $height = $image_h;
    }

    list ($new_x, $new_y) = static::CalculateOffset($x, $y, $image_w, $image_h, $width, $height);

    // Прозрачность
    $new_image = imagecreatetruecolor($width, $height);
    imagealphablending($new_image, true);
    imagefill($new_image, 0, 0, imagecolorallocatealpha($new_image, 0, 0, 0, 127));
    imagesavealpha($new_image, true);

    if (!imagecopy($new_image, $image, 0, 0, $new_x, $new_y, $width, $height)) {
      return false;
    }

    return $new_image;
  }

  /**
   * Наложение водяного знака. Возвращает GD-ресурс
   * $image - GD-ресурс
   */
  public static function Watermark($image, $watermark, $x = null, $y = null, $image_w = null, $image_h = null)
  {
    // Размеры
    if (is_null($image_w)) {
      $image_w = imagesx($image);
    }
    if (is_null($image_h)) {
      $image_h = imagesy($image);
    }
    if (is_null($x)) {
      $x = 'center';
    }
    if (is_null($y)) {
      $y = 'center';
    }

    $watermark = Image::Me($watermark);
    $width     = $watermark->getWidth();
    $height    = $watermark->getHeight();
    list ($new_x, $new_y) = static::CalculateOffset($x, $y, $image_w, $image_h, $width, $height);

    if (!imagecopy($image, $watermark->getImage(), $new_x, $new_y, 0, 0, $width, $height)) {
      return false;
    }

    return $image;
  }

  /**
   * Создание выходного изображения в соотв. с настройками
   * $file - файл для сохранения. Если не указан - вывод в STDOUT
   * $type - тип изображения
   * $quality - качество изображения, для JPG: от 0 до 100, для PNG: от 9 до 0
   */
  public static function Make($image, $filename = null, $type = null, $quality = null)
  {
    //Если тип не указан, пробуем определить исходя из имени файла
    if (is_null($type)) {
      $type = static::GetTypeByFileName($filename) ? : IMG_JPG;
    }

    if (!is_numeric($type)) {
      $type = static::GetTypeByExtension($type);
    }

    switch ($type) {
      case IMG_JPG:
        $ctype = 'image/jpg';
        $res   = imagejpeg($image, $filename, $quality ? : static::QUALITY_JPG);
        break;
      case IMG_PNG:
        $ctype = 'image/png';
        $res   = imagepng($image, $filename, $quality ? : static::QUALITY_PNG);
        break;
      case IMG_GIF:
        $ctype = 'image/gif';
        $res   = imagegif($image, $filename);
        break;
      default:
        throw new Exception('Неизвестный тип изображения для создания');
    }
    if (is_null($filename)) {
      header('Content-Type: ' . $ctype);
    }

    return $res;
  }

  /** Получение типа файла по имени файла */
  public static function GetTypeByFileName($file)
  {
    if (empty ($file)) {
      return false;
    }
    $ext = static::GetFileExtension($file);

    return static::GetTypeByExtension($ext);
  }

  /** Получение типа изображения по расширению */
  public static function GetTypeByExtension($ext)
  {
    switch (strtolower($ext)) {
      case 'jpg':
      case 'jpeg':
        return IMG_JPG;
        break;
      case 'png':
        return IMG_PNG;
        break;
      case 'gif':
        return IMG_GIF;
        break;
    }
    return false;
  }

  /** Получение расширения файла из полного пути */
  public static function GetFileExtension($file)
  {
    $filename = array_pop(explode(DIRECTORY_SEPARATOR, $file));

    return strpos($filename, '.') !== false ? strtolower(array_pop(explode('.', $filename))) : false;
  }

  /** Получение расширения по MIME-типу */
  public static function GetExtensionByMimeType($mime)
  {
    switch ($mime) {
      case 'image/jpg':
        return 'jpg';
        break;
      case 'image/jpeg':
        return 'jpg';
        break;
      case 'image/gif':
        return 'gif';
        break;
      case 'image/png':
        return 'png';
        break;
    }
  }

  /** Применение цепочки модификаторов к изображению */
  protected function applyModifiers()
  {
    if (count($this->modify_arr) > 0) {
      foreach ($this->modify_arr as $config) {
        switch ($config['method']) {
          case static::RESIZE:
            $this->image = static::Resize(
              $this->image, $config['width'], $config['height'], $this->getWidth(), $this->getHeight()
            );
            break;
          case static::CROP:
            $this->image = static::Crop($this->image, $config['width'], $config['height'], $config['x'], $config['y']);
            break;
          case static::WATERMARK:
            $this->image = static::Watermark($this->image, $config['file'], $config['x'], $config['y']);
            break;
        }
      }
    }

    return $this;
  }
}