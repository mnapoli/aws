<?php

namespace AsyncAws\S3\Result;

use AsyncAws\Core\Exception\InvalidArgument;
use AsyncAws\Core\Response;
use AsyncAws\Core\Result;
use AsyncAws\S3\Enum\EncodingType;
use AsyncAws\S3\Input\ListObjectsV2Request;
use AsyncAws\S3\S3Client;
use AsyncAws\S3\ValueObject\AwsObject;
use AsyncAws\S3\ValueObject\CommonPrefix;
use AsyncAws\S3\ValueObject\Owner;

/**
 * @implements \IteratorAggregate<AwsObject|CommonPrefix>
 */
class ListObjectsV2Output extends Result implements \IteratorAggregate
{
    /**
     * Set to false if all of the results were returned. Set to true if more keys are available to return. If the number of
     * results exceeds that specified by MaxKeys, all of the results might not be returned.
     */
    private $IsTruncated;

    /**
     * Metadata about each object returned.
     */
    private $Contents = [];

    /**
     * The bucket name.
     */
    private $Name;

    /**
     * Keys that begin with the indicated prefix.
     */
    private $Prefix;

    /**
     * Causes keys that contain the same string between the prefix and the first occurrence of the delimiter to be rolled up
     * into a single result element in the CommonPrefixes collection. These rolled-up keys are not returned elsewhere in the
     * response. Each rolled-up result counts as only one return against the `MaxKeys` value.
     */
    private $Delimiter;

    /**
     * Sets the maximum number of keys returned in the response. By default the API returns up to 1,000 key names. The
     * response might contain fewer keys but will never contain more.
     */
    private $MaxKeys;

    /**
     * All of the keys rolled up into a common prefix count as a single return when calculating the number of returns.
     */
    private $CommonPrefixes = [];

    /**
     * Encoding type used by Amazon S3 to encode object key names in the XML response.
     */
    private $EncodingType;

    /**
     * KeyCount is the number of keys returned with this request. KeyCount will always be less than equals to MaxKeys field.
     * Say you ask for 50 keys, your result will include less than equals 50 keys.
     */
    private $KeyCount;

    /**
     * If ContinuationToken was sent with the request, it is included in the response.
     */
    private $ContinuationToken;

    /**
     * `NextContinuationToken` is sent when `isTruncated` is true, which means there are more keys in the bucket that can be
     * listed. The next list requests to Amazon S3 can be continued with this `NextContinuationToken`.
     * `NextContinuationToken` is obfuscated and is not a real key.
     */
    private $NextContinuationToken;

    /**
     * If StartAfter was sent with the request, it is included in the response.
     */
    private $StartAfter;

    /**
     * @param bool $currentPageOnly When true, iterates over items of the current page. Otherwise also fetch items in the next pages.
     *
     * @return iterable<CommonPrefix>
     */
    public function getCommonPrefixes(bool $currentPageOnly = false): iterable
    {
        if ($currentPageOnly) {
            $this->initialize();
            yield from $this->CommonPrefixes;

            return;
        }

        $client = $this->awsClient;
        if (!$client instanceof S3Client) {
            throw new InvalidArgument('missing client injected in paginated result');
        }
        if (!$this->input instanceof ListObjectsV2Request) {
            throw new InvalidArgument('missing last request injected in paginated result');
        }
        $input = clone $this->input;
        $page = $this;
        while (true) {
            if ($page->getNextContinuationToken()) {
                $input->setContinuationToken($page->getNextContinuationToken());

                $this->registerPrefetch($nextPage = $client->ListObjectsV2($input));
            } else {
                $nextPage = null;
            }

            yield from $page->getCommonPrefixes(true);

            if (null === $nextPage) {
                break;
            }

            $this->unregisterPrefetch($nextPage);
            $page = $nextPage;
        }
    }

    /**
     * @param bool $currentPageOnly When true, iterates over items of the current page. Otherwise also fetch items in the next pages.
     *
     * @return iterable<AwsObject>
     */
    public function getContents(bool $currentPageOnly = false): iterable
    {
        if ($currentPageOnly) {
            $this->initialize();
            yield from $this->Contents;

            return;
        }

        $client = $this->awsClient;
        if (!$client instanceof S3Client) {
            throw new InvalidArgument('missing client injected in paginated result');
        }
        if (!$this->input instanceof ListObjectsV2Request) {
            throw new InvalidArgument('missing last request injected in paginated result');
        }
        $input = clone $this->input;
        $page = $this;
        while (true) {
            if ($page->getNextContinuationToken()) {
                $input->setContinuationToken($page->getNextContinuationToken());

                $this->registerPrefetch($nextPage = $client->ListObjectsV2($input));
            } else {
                $nextPage = null;
            }

            yield from $page->getContents(true);

            if (null === $nextPage) {
                break;
            }

            $this->unregisterPrefetch($nextPage);
            $page = $nextPage;
        }
    }

    public function getContinuationToken(): ?string
    {
        $this->initialize();

        return $this->ContinuationToken;
    }

    public function getDelimiter(): ?string
    {
        $this->initialize();

        return $this->Delimiter;
    }

    /**
     * @return EncodingType::*|null
     */
    public function getEncodingType(): ?string
    {
        $this->initialize();

        return $this->EncodingType;
    }

    public function getIsTruncated(): ?bool
    {
        $this->initialize();

        return $this->IsTruncated;
    }

    /**
     * Iterates over Contents then CommonPrefixes.
     *
     * @return \Traversable<AwsObject|CommonPrefix>
     */
    public function getIterator(): \Traversable
    {
        $client = $this->awsClient;
        if (!$client instanceof S3Client) {
            throw new InvalidArgument('missing client injected in paginated result');
        }
        if (!$this->input instanceof ListObjectsV2Request) {
            throw new InvalidArgument('missing last request injected in paginated result');
        }
        $input = clone $this->input;
        $page = $this;
        while (true) {
            if ($page->getNextContinuationToken()) {
                $input->setContinuationToken($page->getNextContinuationToken());

                $this->registerPrefetch($nextPage = $client->ListObjectsV2($input));
            } else {
                $nextPage = null;
            }

            yield from $page->getContents(true);
            yield from $page->getCommonPrefixes(true);

            if (null === $nextPage) {
                break;
            }

            $this->unregisterPrefetch($nextPage);
            $page = $nextPage;
        }
    }

    public function getKeyCount(): ?int
    {
        $this->initialize();

        return $this->KeyCount;
    }

    public function getMaxKeys(): ?int
    {
        $this->initialize();

        return $this->MaxKeys;
    }

    public function getName(): ?string
    {
        $this->initialize();

        return $this->Name;
    }

    public function getNextContinuationToken(): ?string
    {
        $this->initialize();

        return $this->NextContinuationToken;
    }

    public function getPrefix(): ?string
    {
        $this->initialize();

        return $this->Prefix;
    }

    public function getStartAfter(): ?string
    {
        $this->initialize();

        return $this->StartAfter;
    }

    protected function populateResult(Response $response): void
    {
        $data = new \SimpleXMLElement($response->getContent());
        $this->IsTruncated = ($v = $data->IsTruncated) ? filter_var((string) $v, \FILTER_VALIDATE_BOOLEAN) : null;
        $this->Contents = !$data->Contents ? [] : $this->populateResultObjectList($data->Contents);
        $this->Name = ($v = $data->Name) ? (string) $v : null;
        $this->Prefix = ($v = $data->Prefix) ? (string) $v : null;
        $this->Delimiter = ($v = $data->Delimiter) ? (string) $v : null;
        $this->MaxKeys = ($v = $data->MaxKeys) ? (int) (string) $v : null;
        $this->CommonPrefixes = !$data->CommonPrefixes ? [] : $this->populateResultCommonPrefixList($data->CommonPrefixes);
        $this->EncodingType = ($v = $data->EncodingType) ? (string) $v : null;
        $this->KeyCount = ($v = $data->KeyCount) ? (int) (string) $v : null;
        $this->ContinuationToken = ($v = $data->ContinuationToken) ? (string) $v : null;
        $this->NextContinuationToken = ($v = $data->NextContinuationToken) ? (string) $v : null;
        $this->StartAfter = ($v = $data->StartAfter) ? (string) $v : null;
    }

    /**
     * @return CommonPrefix[]
     */
    private function populateResultCommonPrefixList(\SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml as $item) {
            $items[] = new CommonPrefix([
                'Prefix' => ($v = $item->Prefix) ? (string) $v : null,
            ]);
        }

        return $items;
    }

    /**
     * @return AwsObject[]
     */
    private function populateResultObjectList(\SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml as $item) {
            $items[] = new AwsObject([
                'Key' => ($v = $item->Key) ? (string) $v : null,
                'LastModified' => ($v = $item->LastModified) ? new \DateTimeImmutable((string) $v) : null,
                'ETag' => ($v = $item->ETag) ? (string) $v : null,
                'Size' => ($v = $item->Size) ? (string) $v : null,
                'StorageClass' => ($v = $item->StorageClass) ? (string) $v : null,
                'Owner' => !$item->Owner ? null : new Owner([
                    'DisplayName' => ($v = $item->Owner->DisplayName) ? (string) $v : null,
                    'ID' => ($v = $item->Owner->ID) ? (string) $v : null,
                ]),
            ]);
        }

        return $items;
    }
}
