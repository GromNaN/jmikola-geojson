<?php

declare(strict_types=1);

namespace GeoJson\Tests;

use GeoJson\BoundingBox;
use GeoJson\CoordinateReferenceSystem\Named;
use GeoJson\Exception\UnserializationException;
use GeoJson\GeoJson;
use GeoJson\Geometry\Point;
use GeoJson\JsonUnserializable;
use JsonSerializable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function get_class;
use function get_debug_type;
use function gettype;
use function is_object;
use function json_decode;

class GeoJsonTest extends TestCase
{
    public function testIsJsonSerializable(): void
    {
        $this->assertInstanceOf(JsonSerializable::class, $this->createMock(GeoJson::class));
    }

    public function testIsJsonUnserializable(): void
    {
        $this->assertInstanceOf(JsonUnserializable::class, $this->createMock(GeoJson::class));
    }

    #[DataProvider('provideJsonDecodeAssocOptions')]
    #[Group('functional')]
    public function testUnserializationWithBoundingBox($assoc): void
    {
        $json = <<<'JSON'
{
    "type": "Point",
    "coordinates": [1, 1],
    "bbox": [-180.0, -90.0, 180.0, 90.0]
}
JSON;

        $json = json_decode($json, $assoc);
        $point = GeoJson::jsonUnserialize($json);

        $this->assertInstanceOf(Point::class, $point);
        $this->assertSame(GeoJson::TYPE_POINT, $point->getType());
        $this->assertSame([1, 1], $point->getCoordinates());

        $boundingBox = $point->getBoundingBox();

        $this->assertInstanceOf(BoundingBox::class, $boundingBox);
        $this->assertSame([-180.0, -90.0, 180.0, 90.0], $boundingBox->getBounds());
    }

    #[DataProvider('provideJsonDecodeAssocOptions')]
    #[Group('functional')]
    public function testUnserializationWithCrs($assoc): void
    {
        $json = <<<'JSON'
{
    "type": "Point",
    "coordinates": [1, 1],
    "crs": {
        "type": "name",
        "properties": {
            "name": "urn:ogc:def:crs:OGC:1.3:CRS84"
        }
    }
}
JSON;

        $json = json_decode($json, $assoc);
        $point = GeoJson::jsonUnserialize($json);

        $this->assertInstanceOf(Point::class, $point);
        $this->assertSame(GeoJson::TYPE_POINT, $point->getType());
        $this->assertSame([1, 1], $point->getCoordinates());

        $crs = $point->getCrs();

        $expectedProperties = ['name' => 'urn:ogc:def:crs:OGC:1.3:CRS84'];

        $this->assertInstanceOf(Named::class, $crs);
        $this->assertSame('name', $crs->getType());
        $this->assertSame($expectedProperties, $crs->getProperties());
    }

    public function testUnserializationWithInvalidArgument(): void
    {
        $this->expectException(UnserializationException::class);
        $this->expectExceptionMessage('GeoJson expected value of type array or object, string given');

        GeoJson::jsonUnserialize('must be array or object, but this is a string');
    }

    public function testUnserializationWithUnknownType(): void
    {
        $this->expectException(UnserializationException::class);
        $this->expectExceptionMessage('Invalid GeoJson type "Unknown"');

        GeoJson::jsonUnserialize(['type' => 'Unknown']);
    }

    public function testUnserializationWithMissingType(): void
    {
        $this->expectException(UnserializationException::class);
        $this->expectExceptionMessage('GeoJson expected "type" property of type string, none given');

        GeoJson::jsonUnserialize([]);
    }

    #[DataProvider('provideGeoJsonTypesWithCoordinates')]
    public function testUnserializationWithMissingCoordinates(string $type): void
    {
        $this->expectException(UnserializationException::class);
        $this->expectExceptionMessage($type . ' expected "coordinates" property of type array, none given');

        GeoJson::jsonUnserialize([
            'type' => $type,
        ]);
    }

    /** @param mixed $value */
    #[DataProvider('provideInvalidCoordinates')]
    public function testUnserializationWithInvalidCoordinates($value): void
    {
        $valueType = get_debug_type($value);

        $this->expectException(UnserializationException::class);
        $this->expectExceptionMessage('Point expected "coordinates" property of type array, ' . $valueType . ' given');

        GeoJson::jsonUnserialize([
            'type' => GeoJson::TYPE_POINT,
            'coordinates' => $value,
        ]);
    }

    public function testFeatureUnserializationWithInvalidGeometry(): void
    {
        $this->expectException(UnserializationException::class);
        $this->expectExceptionMessage('Feature expected "geometry" property of type array or object, string given');

        GeoJson::jsonUnserialize([
            'type' => GeoJson::TYPE_FEATURE,
            'geometry' => 'must be array or object, but this is a string',
        ]);
    }

    public function testFeatureUnserializationWithInvalidProperties(): void
    {
        $this->expectException(UnserializationException::class);
        $this->expectExceptionMessage('Feature expected "properties" property of type array or object, string given');

        GeoJson::jsonUnserialize([
            'type' => GeoJson::TYPE_FEATURE,
            'properties' => 'must be array or object, but this is a string',
        ]);
    }

    public static function provideJsonDecodeAssocOptions()
    {
        return [
            'assoc=true' => [true],
            'assoc=false' => [false],
        ];
    }

    public static function provideGeoJsonTypesWithCoordinates()
    {
        return [
            GeoJson::TYPE_LINE_STRING => [GeoJson::TYPE_LINE_STRING],
            GeoJson::TYPE_MULTI_LINE_STRING => [GeoJson::TYPE_MULTI_LINE_STRING],
            GeoJson::TYPE_MULTI_POINT => [GeoJson::TYPE_MULTI_POINT],
            GeoJson::TYPE_MULTI_POLYGON => [GeoJson::TYPE_MULTI_POLYGON],
            GeoJson::TYPE_POINT => [GeoJson::TYPE_POINT],
            GeoJson::TYPE_POLYGON => [GeoJson::TYPE_POLYGON],
        ];
    }

    public static function provideInvalidCoordinates()
    {
        return [
            'string' => ['1,1'],
            'int' => [1],
            'bool' => [false],
        ];
    }
}
