<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 基于mpdf导出pdf的插件:
 * 目前生成的pdf在主流pdf阅读器中目录不能跳转
 * 
 * 
 * @package PDFExport
 * @author Kaitoven Chen
 * @version 1.0.1
 * @link https://www.chendk.info
 */
class PDFExport_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Archive')->singleHandle = array('PDFExport_Plugin', 'addPDFExportButton');
        Typecho_Plugin::factory('Widget_Archive')->beforeRender = array('PDFExport_Plugin', 'checkPDFExportRequest');
        return _t('PDF 预览插件已激活');
    }

    public static function deactivate() {
        return _t('PDF 预览插件已禁用');
    }

    public static function config(Typecho_Widget_Helper_Form $form) {}
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}
    public static function uninstall() {}

    public static function addPDFExportButton($archive)
    {
        if ($archive->request->get('pdf_Export') == 1) return;

        if ($archive->is('post') || ($archive->is('page') && $archive->slug == 'about')) {
            $currentUrl = $archive->permalink . '?pdf_Export=1';
            echo '<script type="text/javascript">
                window.addEventListener("load", function() {
                    var pdfButton = document.createElement("a");
                    pdfButton.href = "' . htmlspecialchars($currentUrl) . '";
                    pdfButton.target = "_blank";
                    pdfButton.title = "Export PDF";
                    pdfButton.style.position = "fixed";
                    pdfButton.style.bottom = "75px";
                    pdfButton.style.right = "20px";
                    pdfButton.style.color = "white";
                    pdfButton.style.padding = "15px";
                    pdfButton.style.borderRadius = "50%";
                    pdfButton.style.textDecoration = "none";
                    pdfButton.style.zIndex = "1000";

                    var img = document.createElement("img");
                    img.src = "https://www.chendk.info/usr/themes/jasmine/pictures/s4.png";
                    img.alt = "PDF 预览";
                    img.style.width = "auto";
                    img.style.height = "50px";
                    pdfButton.appendChild(img);
                    document.body.appendChild(pdfButton);

                    var pdfToast = document.createElement("div");
                    pdfToast.textContent = "You Can Export PDF In The Bottom Right Corner";
                    pdfToast.style.position = "fixed";
                    pdfToast.style.top = "50%";
                    pdfToast.style.left = "50%";
                    pdfToast.style.transform = "translate(-50%, -50%)";
                    pdfToast.style.backgroundColor = "rgba(0, 0, 0, 0.6)";
                    pdfToast.style.color = "white";
                    pdfToast.style.padding = "1.5vh 2vw";
                    pdfToast.style.borderRadius = "5px";
                    pdfToast.style.fontSize = "2.5vh";
                    pdfToast.style.zIndex = "1000";
                    pdfToast.style.opacity = "0";
                    pdfToast.style.transition = "opacity 1.2s";
                    document.body.appendChild(pdfToast);

                    setTimeout(function() {
                        pdfToast.style.opacity = "1";
                    }, 500);

                    setTimeout(function() {
                        pdfToast.style.opacity = "0";
                        setTimeout(function() {
                            pdfToast.remove();
                        }, 500);
                    }, 3500);
                });
            </script>';
        }
    }

    public static function checkPDFExportRequest($archive)
    {
        if ($archive->request->get('pdf_Export') == 1 && $archive->is('single')) {
            self::generatePDF($archive);
            exit;
        }
    }

    private static function generatePDF($archive)
    {
        require_once __DIR__ . '/vendor/autoload.php';

        $title = $archive->title;
        $content = $archive->content;
        $author = $archive->author->screenName;
        $date = date('Y-m-d');

        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => __DIR__ . '/tmp',
            'default_font' => 'noto',
            'format' => 'A4',
            'margin_top' => 30,
            'margin_bottom' => 30,
            'margin_left' => 15,
            'margin_right' => 15
        ]);

        $mpdf->fontdata['noto'] = [
            'R' => __DIR__ . '/fonts/NotoSansSC-Regular.ttf',
            'B' => __DIR__ . '/fonts/NotoSansSC-Bold.ttf',
        ];
        $mpdf->SetDefaultFont('noto');

        $mpdf->SetHTMLHeader('
            <div style="text-align: center; font-weight: bold; font-size: 10pt; margin-bottom: 2px;">' . htmlspecialchars($title) . '</div>
            <hr style="border: 0; height: 2px; background-color: #000; margin-top: 0;">
        ');

        $mpdf->SetHTMLFooter('<div style="text-align: center; font-size: 10pt;">' . $date . '</div>');
        $mpdf->SetWatermarkText($author, 0.1);
        $mpdf->showWatermarkText = true;

        $css = '
        <style>
        body { font-family: noto, sans-serif; font-size: 12pt; line-height: 1.6; color: #333; }
        h1 { font-size: 24pt; margin-bottom: 20px; text-align: center; color: #FF5733; }
        h2, h3, h4, h5, h6 { font-weight: bold; color: #007bff; }
        p { margin-bottom: 12px; text-align: justify; }
        pre {
            background-color: #f8f9fa;
            border: 1px solid #e1e1e8;
            padding: 10px;
            border-radius: 5px;
            overflow: auto;
        }
        code {
            color: #e83e8c;
            background-color: #f8f9fa;
            padding: 3px 5px;
            border-radius: 3px;
        }
        blockquote {
            border-left: 3px solid #007bff;
            margin: 10px 0;
            padding-left: 10px;
            color: #555;
        }
        img {
            display: block;
            margin: 10px auto;
            max-width: 100%;
        }
        </style>';
        $mpdf->WriteHTML($css);

        // 处理 GIF 图像
        $content = preg_replace_callback('/<img.*?src=["\'](.*?)["\'].*?>/i', function ($matches) {
            $imageSrc = $matches[1];
            if (preg_match('/\.gif$/i', $imageSrc)) {
                $firstFrame = self::getGifFirstFrame($imageSrc);
                return '<a href="' . htmlspecialchars($imageSrc) . '" target="_blank">
                            <img src="' . htmlspecialchars($firstFrame) . '" alt="点击查看动态图" />
                        </a>';
            }
            return $matches[0];
        }, $content);

        $html = '<h1>' . htmlspecialchars($title) . '</h1>' . $content;

        // 拆分段落并写入 + Bookmark
        $segments = preg_split('/(<h[1-6]>.*?<\/h[1-6]>)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($segments as $segment) {
            if (preg_match('/<h([1-6])>(.*?)<\/h\1>/i', $segment, $match)) {
                $level = intval($match[1]);
                $text = strip_tags($match[2]);
                $mpdf->WriteHTML($segment);
                $mpdf->Bookmark($text, $level - 1);
            } else {
                $mpdf->WriteHTML($segment);
            }
        }

        $mpdf->Output($title . '.pdf', 'D');
        exit;
    }

    private static function getGifFirstFrame($gifUrl)
    {
        $image = imagecreatefromgif($gifUrl);
        $outputPath = __DIR__ . '/tmp/first_frame.png';
        imagepng($image, $outputPath);
        imagedestroy($image);
        return $outputPath;
    }
}
