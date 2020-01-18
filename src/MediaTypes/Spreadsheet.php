<?php


namespace Luracast\Restler\MediaTypes;


use Box\Spout\Common\Entity\Style\Color;
use Box\Spout\Writer\Common\Creator\Style\StyleBuilder;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Luracast\Restler\Contracts\DownloadableFileMediaTypeInterface;
use Luracast\Restler\Contracts\ResponseMediaTypeInterface;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\ResponseHeaders;
use Luracast\Restler\Utils\Convert;
use Luracast\Restler\Defaults;


class Spreadsheet extends Dependent implements DownloadableFileMediaTypeInterface
{
    const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    const EXTENSION = 'xlsx';

    /**
     * @return array {@type associative}
     *               CLASS_NAME => vendor/project:version
     */
    public function dependencies()
    {
        return [
            'Box\Spout\Common\Entity\Row' => 'box/spout:dev-master'
        ];
    }

    public function __construct(Convert $convert)
    {
        parent::__construct($convert);
    }

    /**
     * @inheritDoc
     */
    public function encode($data, ResponseHeaders $responseHeaders, bool $humanReadable = false)
    {
        $data = $this->convert->toArray($data);
        if (!is_writable(Defaults::$cacheDirectory)) {
            throw new HttpException(500, 'Spreadsheet needs Defaults::$cacheDirectory to be writable.');
        }
        if (is_array($data) && array_values($data) == $data) {
            //if indexed array
            $file = Defaults::$cacheDirectory . '/spreadsheet' . microtime() . '.' . $this->extension();
            $writer = WriterEntityFactory::createWriter($this->extension());
            $writer->openToFile($file);
            $row = array_shift($data);
            $style = (new StyleBuilder)->setFontBold()->setBackgroundColor(Color::rgb(230, 230, 230))->build();
            if (array_values($row) == $row) {
                //write header with first row values
                $writer->addRow(WriterEntityFactory::createRowFromArray(array_values($row), $style));
            } else {
                //write header with keys
                $writer->addRow(WriterEntityFactory::createRowFromArray(array_keys($row), $style));
                $writer->addRow(WriterEntityFactory::createRowFromArray(array_values($row)));
            }
            foreach ($data as $row) {
                $writer->addRow(WriterEntityFactory::createRowFromArray(array_values($row)));
            }
            $writer->close();
            $export = file_get_contents($file);
            unlink($file);
            return $export;
        }
        throw new HttpException(500, 'Unsupported data for ' . strtoupper($this->extension()) . ' MediaType');
    }
}
