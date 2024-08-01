<?php

namespace Larowlan\Tl;

use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Formatter for formatting Summary objects as JSON.
 */
class SummaryJsonFormatter {

  /**
   * Formats a summary as JSON.
   */
  public static function formatJson(Summary $summary): string {
    $encoders = [new JsonEncoder()];
    $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
    $normalizers = [new ObjectNormalizer($classMetadataFactory)];
    $serializer = new Serializer($normalizers, $encoders);
    $data = $serializer->normalize($summary, NULL, ['groups' => 'summary']);
    return $serializer->serialize($data, JsonEncoder::FORMAT, [JsonEncode::OPTIONS => JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT]);
  }

}
