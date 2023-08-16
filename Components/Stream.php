<?php

namespace Kaa\HttpClient\Components;

use Throwable;
use Kaa\HttpClient\Contracts\ChunkInterface;
use Kaa\HttpClient\Components\Exception\{TransportException, InvalidArgumentException};

class Stream
{
    private ?float $timeout;

    /** @var RunningResponses[] */
    private array $runningResponses;

    /** @var int[] $runningResponsesKeys contains an array of all array keys*/
    private array $runningResponsesKeys;

    private int $runningResponsesKeysIndex;

    private int $runningResponsesKey;               // stores the index of the current array element

    private CurlClientState $currentMulti;         // stores a $multi of the current runningResponses array element

    /** @var int[]  */
    private array $responsesKeys;

    private int $responsesKeysIndex;

    private int $responseKey;

    private ?CurlResponse $currentResponse;

    private bool $isEmpty;

    private bool $isNewResponses;

    private bool $isNewResponse;

    private ?ChunkInterface $chunk;

    private int $functionIndex;

    private float $lastActivity;

    private float $elapsedTimeout;

    private bool $hasActivity;

    private float $timeoutMax;

    private float $timeoutMin;

    public function __construct(array $responses, ?float $timeout = null)
    {
        $this->runningResponses = [];
        $this->timeout = $timeout;

        foreach ($responses as $response) {
            CurlResponse::schedule($response, $this->runningResponses);
        }

        if ($this->runningResponses === []) {
            $this->isEmpty = true;
            return;
        }
        $this->isEmpty = false;
        $this->lastActivity = microtime(true);
        $this->elapsedTimeout = 0;
        $this->functionIndex = 0;
        $this->prepareCycle();
    }

    public function stream(): ?ChunkInterface
    {
        if ($this->isEmpty) {
            return $this->finishRunningResponses();
        } elseif ($this->isNewResponses) {
            CurlResponse::perform(
                $this->currentMulti,
                $this->runningResponses[$this->runningResponsesKey]->responses,
                $this->responseKey
            );
            $this->isNewResponses = false;
            if (! $this->setNewResponses()) {
                return $this->finishResponses();
            } else {
                $this->isNewResponse = true;
                return $this->stream();
            }
        } elseif ($this->isNewResponse) {
            $this->timeoutMax = $this->timeout ?? max($this->timeoutMax, $this->currentResponse->getTimeout());
            $this->timeoutMin = min($this->timeoutMin, $this->currentResponse->getTimeout(), 1);
            $this->chunk = null;

            if (isset($this->currentMulti->handlesActivity[$this->responseKey])) {
                // no-op
            } elseif (! isset($this->currentMulti->openHandles[$this->responseKey])) {
                unset($this->runningResponses[$this->runningResponsesKey]->responses[$this->responseKey]);
                if ($this->hasNextResponse()) {
                    $this->setNextResponse();
                    return $this->stream();
                } else {
                    return $this->finishResponses();
                }
            } elseif ($this->elapsedTimeout >= $this->timeoutMax) {
                $this->currentMulti->handlesActivity[$this->responseKey] = [
                    (new HandleActivity())->setChunk(new ErrorChunk(
                        $this->currentResponse->getOffset(),
                        null,
                        sprintf('Idle timeout reached for "%s".', $this->currentResponse->getInfo('url'))
                    ))
                ];
            } elseif ($this->hasNextResponse()) {
                $this->setNextResponse();
                return $this->stream();
            } else {
                return $this->finishResponses();
            }

            $this->isNewResponse = false;
            return $this->stream();
        } elseif ($this->currentMulti->handlesActivity[$this->responseKey] ?? false) {
            return $this->getChunk($this->currentMulti->handlesActivity[$this->responseKey]);
        } else {
            return $this->finishActivity();
        }
    }

    /**
     * @param HandleActivity[] $handleActivity
     * @return ChunkInterface|null
     * @throws TransportException
     */
    private function getChunk(array &$handleActivity): ?ChunkInterface
    {
        switch ($this->functionIndex) {
            case 0:
                $this->hasActivity = true;
                $this->elapsedTimeout = 0;

                if ((bool) $stringChunk = ($tempChunk = array_shift($handleActivity))->getActivityMessage()) {
                    if (
                        '' !== $stringChunk
                        && null !== $this->currentResponse->getContent()
                        && strlen($stringChunk) !== fwrite($this->currentResponse->getContentParam(), $stringChunk)
                    ) {
                        $handleActivity = [
                            new HandleActivity(),
                            (new HandleActivity())->setException(new TransportException('Error while processing content unencoding.'))
                        ];
                        return $this->getChunk($handleActivity);
                    }
                    $chunkLen = \strlen($stringChunk);
                    $this->chunk = new DataChunk($this->currentResponse->getOffset(), $stringChunk);
                    $this->currentResponse->setOffset($this->currentResponse->getOffset() + $chunkLen);
                } elseif ($tempChunk->isNull()) {
                    /** @var Throwable $e */
                    $e = $handleActivity[0]->getActivityException() ?: $handleActivity[0]->getActivityError();
                    unset(
                        $this->runningResponses[$this->runningResponsesKey]->responses[$this->responseKey],
                        $this->currentMulti->handlesActivity[$this->responseKey]
                    );
                    $this->setNewResponses();
                    $this->currentResponse->close();

                    if (null !== $e) {
                        // Because we are sure, that the object implements Throwable, we can use getMessage.
                        $this->currentResponse->setInfoParam('error', $e->getMessage());

                        if ($e instanceof \Error) {
                            throw $e;
                        }

                        $this->chunk = new ErrorChunk($this->currentResponse->getOffset(), $e);
                    } else {
                        $this->chunk = new LastChunk($this->currentResponse->getOffset());
                    }
                } elseif ($tempChunk->getActivityChunk() instanceof ErrorChunk) {
                    unset($this->responses[$this->responseKey]);
                    $this->chunk = $tempChunk->getActivityChunk();
                    $this->elapsedTimeout = $this->timeoutMax;
                } elseif ($tempChunk->getActivityChunk() instanceof FirstChunk) {
                    $this->chunk = $tempChunk->getActivityChunk();
                    // Here should be a code block with logging logic.
                    // .

                    // Here should be a code block with a buffering content logic. Add this!!!
                    // .
                    $this->functionIndex = 1;
                }
                return $this->chunk;

            case 1:
                if ($this->currentResponse->getInitializer() && null === $this->currentResponse->getInfoParam(
                        'error'
                    )) {
                    // Ensure the HTTP status code is always checked
                    $this->currentResponse->getHeaders(true);
                }
                $this->functionIndex = 0;
                return $this->getChunk($handleActivity);
            default:
                $this->functionIndex = 0;
                return $this->getChunk($handleActivity);
        }
    }

    private function finishActivity(): ?ChunkInterface
    {
        unset($this->currentMulti->handlesActivity[$this->responseKey]);
        if ($this->chunk instanceof ErrorChunk && !$this->chunk->didThrow()) {
            $this->chunk->getContent();
        }
        if ($this->hasNextResponse()) {
            $this->setNextResponse();
            return $this->stream();
        } else {
            return $this->finishResponses();
        }
    }

    private function finishResponses(): ?ChunkInterface
    {
        if (! (bool) $this->runningResponses[$this->runningResponsesKey]->responses) {
            unset($this->runningResponses[$this->runningResponsesKey]);
        }
        $this->currentMulti->handlesActivity = $this->currentMulti->handlesActivity ?: [];
        $this->currentMulti->openHandles = $this->currentMulti->openHandles ?: [];

        if ($this->hasNextRunningResponses()) {
            $this->setNextRunningResponses();
            $this->isNewResponses = true;
            return $this->stream();
        } else {
            return $this->finishRunningResponses();
        }
    }

    private function finishRunningResponses(): ?ChunkInterface
    {
        if (! (bool) $this->runningResponses) {
            $this->isEmpty = true;
            return null;
        }

        if ($this->hasActivity) {
            $this->lastActivity = microtime(true);
            $this->prepareCycle();
            return $this->stream();
        }

        if (-1 === CurlResponse::select(
                $this->currentMulti,
                min($this->timeoutMin, $this->timeoutMax - $this->elapsedTimeout))
        ) {
            usleep(min(500, 1E6 * $this->timeoutMin));
        }

        $this->elapsedTimeout = microtime(true) - $this->lastActivity;
        $this->prepareCycle();
        return $this->stream();
    }

    private function hasNextResponse(): bool
    {
        return isset($this->runningResponses[$this->runningResponsesKey]->responses[$this->responsesKeys[$this->responsesKeysIndex + 1]]);
    }

    private function setNextResponse(): void
    {
        $this->responseKey = $this->responsesKeys[++$this->responsesKeysIndex];
        $this->currentResponse = $this->runningResponses[$this->runningResponsesKey]->responses[$this->responseKey];
    }

    private function setNewResponses(): bool
    {
        if (! (bool) $this->runningResponses[$this->runningResponsesKey]->responses) {
            return false;
        }
        if (! defined('IS_PHP')) {
            $this->responsesKeys = array_keys_as_ints($this->runningResponses[$this->runningResponsesKey]->responses);
            $this->responsesKeysIndex = array_first_key($this->responsesKeys);
            $this->responseKey = array_first_value($this->responsesKeys);
        } else {
            #ifndef KPHP
            $this->responsesKeys = array_keys($this->runningResponses[$this->runningResponsesKey]->responses);
            $this->responsesKeysIndex = array_key_first($this->responsesKeys);
            $this->responseKey = $this->responsesKeys[$this->responsesKeysIndex];
            #endif
        }
        $this->currentResponse = $this->runningResponses[$this->runningResponsesKey]->responses[$this->responseKey];
        $this->isNewResponses = true;
        return true;
    }

    private function prepareCycle(): void
    {
        $this->hasActivity = false;
        $this->timeoutMax = 0;
        $this->timeoutMin = $this->timeout ?? INF;

        if (! defined('IS_PHP')) {
            $this->runningResponsesKeys = array_keys_as_ints($this->runningResponses);
            $this->runningResponsesKeysIndex = array_first_key($this->runningResponsesKeys);
            $this->runningResponsesKey = array_first_value($this->runningResponsesKeys);
        } else {
            #ifndef KPHP
            $this->runningResponsesKeys = array_keys($this->runningResponses);
            $this->runningResponsesKeysIndex = array_key_first($this->runningResponsesKeys);
            $this->runningResponsesKey = $this->runningResponsesKeys[$this->runningResponsesKeysIndex];
            #endif
        }
        $this->currentMulti = $this->runningResponses[$this->runningResponsesKey]->getMulti();
        if (! $this->setNewResponses()) {
            throw new InvalidArgumentException('no');
        }
        $this->currentResponse = null;
        $this->isNewResponses = true;
    }

    private function hasNextRunningResponses(): bool
    {
        return isset($this->runningResponses[$this->runningResponsesKeys[$this->runningResponsesKeysIndex+1]]);
    }

    private function setNextRunningResponses(): void
    {
        $this->runningResponsesKey = $this->runningResponsesKeys[++$this->runningResponsesKeysIndex];
        $this->currentMulti = $this->runningResponses[$this->runningResponsesKey]->getMulti();
    }

    public function hasResponses(): bool
    {
        return (bool) $this->runningResponses;
    }
}