<?php

// Simple .ICO parsing.  The ICO format is insanely complex and this may
// fail to correctly handle some technically valid files, but it works
// on the majority I've found.
//
// jimIcon was written in 2013 by Jim Paris <jim@jtan.com> and is
// released under the terms of the CC0:
//
// To the extent possible under law, the author(s) have dedicated all
// copyright and related and neighboring rights to this software to
// the public domain worldwide. This software is distributed without
// any arranty.
//
// You may have received a copy of the CC0 Public Domain Dedication
// along with this software.  If not, see
//   http://creativecommons.org/publicdomain/zero/1.0/

class jimIcon {
        // Get an image color from a string
        function get_color($str, $img) {
                $b = ord($str[0]);
                $g = ord($str[1]);
                $r = ord($str[2]);
                if (strlen($str) > 3) {
                        $a = 127 - (ord($str[3]) / 2);
                        if ($a != 0 && $a != 127)
                                $this->had_alpha = 1;
                } else {
                        $a = 0;
                }
                if ($a != 127)
                        $this->all_transaprent = 0;
                return imagecolorallocatealpha($img, $r, $g, $b, $a);
        }

        // Given a string with the contents of an .ICO,
        // return a GD image of the icon, or false on error.
        function fromiconstring($ico) {
                $this->error = "(unknown error)";
                $this->had_alpha = 0;

                // Read header
                if (strlen($ico) < 6) {
                        $this->error = "too short";
                        return false;
                }
                $h = unpack("vzero/vtype/vnum", $ico);

                // Must be ICO format with at least one image
                if ($h["zero"] != 0 || $h["type"] != 1 || $h["num"] == 0) {
                        // See if we can just parse it with GD directly
                        // if it's not ICO format; maybe it was a mislabeled
                        // PNG or something.
                        $i = @imagecreatefromstring($ico);
                        if ($i) {
                                imagesavealpha($i, true);
                                return $i;
                        }
                        $this->error = "not ICO or other image";
                        return false;
                }

                // Read directory entries to find the biggest image
                $most_pixels = 0;
                for ($i = 0; $i < $h["num"]; $i++) {
                        $entry = substr($ico, 6 + 16 * $i, 16);
                        if (!$entry || strlen($entry) < 16)
                                continue;
                        $e = unpack("Cwidth/" .
                                    "Cheight/" .
                                    "Ccolors/" .
                                    "Czero/" .
                                    "vplanes/" .
                                    "vbpp/" .
                                    "Vsize/" .
                                    "Voffset/",
                                    $entry);
                        if ($e["width"] == 0)
                                $e["width"] = 256;
                        if ($e["height"] == 0)
                                $e["height"] = 256;
                        if ($e["zero"] != 0) {
                                $this->error = "nonzero reserved field";
                                return false;
                        }
                        $pixels = $e["width"] * $e["height"];
                        if ($pixels > $most_pixels) {
                                $most_pixels = $pixels;
                                $most = $e;
                        }
                }
                if ($most_pixels == 0) {
                        $this->error = "no pixels";
                        return false;
                }
                $e = $most;

                // Extract image data
                $data = substr($ico, $e["offset"], $e["size"]);
                if (!$data || strlen($data) != $e["size"]) {
                        $this->error = "bad image data";
                        return false;
                }

                // See if we can parse it (might be PNG format here)
                $i = @imagecreatefromstring($data);
                if ($i) {
                        imagesavealpha($img, true);
                        return $i;
                }

                // Must be a BMP.  Parse it ourselves.
                $img = imagecreatetruecolor($e["width"], $e["height"]);
                imagesavealpha($img, true);
                $bg = imagecolorallocatealpha($img, 255, 0, 0, 127);
                imagefill($img, 0, 0, $bg);

                // Skip over the BITMAPCOREHEADER or BITMAPINFOHEADER;
                // we'll just assume the palette and pixel data follow
                // in the most obvious format as described by the icon
                // directory entry.
                $bitmapinfo = unpack("Vsize", $data);
                if ($bitmapinfo["size"] == 40) {
                        $info = unpack("Vsize/" .
                                       "Vwidth/" .
                                       "Vheight/" .
                                       "vplanes/" .
                                       "vbpp/" .
                                       "Vcompress/" .
                                       "Vsize/" .
                                       "Vxres/" .
                                       "Vyres/" .
                                       "Vpalcolors/" .
                                       "Vimpcolors/", $data);
                        if ($e["bpp"] == 0) {
                                $e["bpp"] = $info["bpp"];
                        }
                }
                $data = substr($data, $bitmapinfo["size"]);

                $height = $e["height"];
                $width = $e["width"];
                $bpp = $e["bpp"];

                // For indexed images, we only support 1, 4, or 8 BPP
                switch ($bpp) {
                case 1:
                case 4:
                case 8:
                        $indexed = 1;
                        break;
                case 24:
                case 32:
                        $indexed = 0;
                        break;
                default:
                        $this->error = "bad BPP $bpp";
                        return false;
                }

                $offset = 0;
                if ($indexed) {
                        $palette = array();
                        $this->all_transparent = 1;
                        for ($i = 0; $i < (1 << $bpp); $i++) {
                                $entry = substr($data, $i * 4, 4);
                                $palette[$i] = $this->get_color($entry, $img);
                        }
                        $offset = $i * 4;

                        // Hack for some icons: if everything was transparent,
                        // discard alpha channel.
                        if ($this->all_transparent) {
                                for ($i = 0; $i < (1 << $bpp); $i++) {
                                        $palette[$i] &= 0xffffff;
                                }
                        }
                }

                // Assume image data follows in bottom-up order.
                // First the "XOR" image
                if ((strlen($data) - $offset) < ($bpp * $height * $width / 8)) {
                        $this->error = "short data";
                        return false;
                }
                $XOR = array();
                for ($y = $height - 1; $y >= 0; $y--) {
                        $x = 0;
                        while ($x < $width) {
                                if (!$indexed) {
                                        $bytes = $bpp / 8;
                                        $entry = substr($data, $offset, $bytes);
                                        $pixel = $this->get_color($entry, $img);
                                        $XOR[$y][$x] = $pixel;
                                        $x++;
                                        $offset += $bytes;
                                } elseif ($bpp == 1) {
                                        $p = ord($data[$offset]);
                                        for ($b = 0x80; $b > 0; $b >>= 1) {
                                                if ($p & $b) {
                                                        $pixel = $palette[1];
                                                } else {
                                                        $pixel = $palette[0];
                                                }
                                                $XOR[$y][$x] = $pixel;
                                                $x++;
                                        }
                                        $offset++;
                                } elseif ($bpp == 4) {
                                        $p = ord($data[$offset]);
                                        $pixel1 = $palette[$p >> 4];
                                        $pixel2 = $palette[$p & 0x0f];
                                        $XOR[$y][$x] = $pixel1;
                                        $XOR[$y][$x+1] = $pixel2;
                                        $x += 2;
                                        $offset++;
                                } elseif ($bpp == 8) {
                                        $pixel = $palette[ord($data[$offset])];
                                        $XOR[$y][$x] = $pixel;
                                        $x += 1;
                                        $offset++;
                                } else {
                                        $this->error = "bad BPP";
                                        return false;
                                }
                        }
                        // End of row padding
                        while ($offset & 3)
                                $offset++;
                }

                // Now the "AND" image, which is 1 bit per pixel.  Ignore
                // if some of our image data already had alpha values,
                // or if there isn't enough data left.
                if ($this->had_alpha ||
                    ((strlen($data) - $offset) < ($height * $width / 8))) {
                        // Just return what we've got
                        for ($y = 0; $y < $height; $y++) {
                                for ($x = 0; $x < $width; $x++) {
                                        imagesetpixel($img, $x, $y,
                                                      $XOR[$y][$x]);
                                }
                        }
                        return $img;
                }

                // Mask what we have with the "AND" image
                for ($y = $height - 1; $y >= 0; $y--) {
                        $x = 0;
                        while ($x < $width) {
                                for ($b = 0x80;
                                     $b > 0 && $x < $width; $b >>= 1) {
                                        if (!(ord($data[$offset]) & $b)) {
                                                imagesetpixel($img, $x, $y,
                                                              $XOR[$y][$x]);
                                        }
                                        $x++;
                                }
                                $offset++;
                        }

                        // End of row padding
                        while ($offset & 3)
                                $offset++;
                }
                return $img;
        }
}
?>