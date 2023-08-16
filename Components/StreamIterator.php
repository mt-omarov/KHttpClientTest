<?php

namespace Kaa\HttpClient\Components;

use Kaa\HttpClient\Contracts\ChunkInterface;
use Throwable;
use Kaa\HttpClient\Components\Exception\TransportException;
use Kaa\HttpClient\Components\Exception\InvalidArgumentException;

class StreamIterator
{
    /** @var RunningResponses[] $runningResponses */
    private array $runningResponses;
    /** @var CurlResponse[] $responses */
    private array $responses;
    private ?float $timeout;

    private float $lastActivity;
    private float $elapsedTimeout;
    private float $timeoutMax;
    private float $timeoutMin;
    private bool $hasActivity;
    private bool $isEmpty;
    private bool $isResponsesEmpty;

    private bool $isNewResponse;

    private CurlClientState $multi;             // current multiHandle element of current $runningResponses array element
    private CurlResponse $currentResponse;      // current element of $runningResponses->responses array. Equal to $runningResponses[$currentRunningResponseIndex]->responses[$responsesKeys[$currentResponsesKeysIndex]]
    /** @var ?int[] $responsesKeys */
    private array $responsesKeys;               // array of all $runningResponses[$currentRunningResponseIndex]->responses keys, because responses array is not indexed sequentially
    private int $currentResponsesKeysIndex;

    private int $currentResponseKey;            // copy of $responsesKeys[$currentResponsesKeysIndex] value
    private int $currentRunningResponsesIndex;  // current index of $runningResponses array

    private int $functionIndex;
    private ?ChunkInterface $chunk;

    /**
     * @param array<CurlResponse> $responses
     * @param float|null $timeout
     * @throws InvalidArgumentException
     */
    public function __construct(array $responses, ?float $timeout = null)
    {
        $this->responses = $responses;
        $this->runningResponses = [];
        $this->timeout = $timeout;
        $this->functionIndex = 0;

        $this->isEmpty = false;
        $this->isResponsesEmpty = false;

        foreach ($responses as $response) {
            CurlResponse::schedule($response, $this->runningResponses);
        }

        $this->lastActivity = microtime(true);
        $this->elapsedTimeout = 0;
        $this->timeoutMax = 0;
        $this->timeoutMin = $this->timeout ?? INF;

        if (defined("IS_PHP")) {
            #ifndef KPHP
            $this->currentRunningResponsesIndex = (int) array_key_first($this->runningResponses) ?: -1;
            #endif
        } else {
            $this->currentRunningResponsesIndex = (int) array_first_key($this->runningResponses) ?: -1;
        }

        // if currentRunningResponsesIndex === -1, than there is no runningResponses and we should execute.
        if ($this->currentRunningResponsesIndex === -1) {
            $this->isEmpty = true;
            return;
        }
        $this->multi = $this->runningResponses[$this->currentRunningResponsesIndex]->getMulti();
        CurlResponse::perform($this->multi, $this->runningResponses[$this->currentRunningResponsesIndex]->responses);

        if (defined("IS_PHP")) {
            #ifndef KPHP
            $this->responsesKeys = array_keys(
                $this->runningResponses[$this->currentRunningResponsesIndex]->responses
            ) ?: null;
            #endif
        } else {
            $this->responsesKeys = array_keys_as_ints(
                $this->runningResponses[$this->currentRunningResponsesIndex]->responses
            ) ?: null;
        }

        if (!$this->responsesKeys) {
            $this->isResponsesEmpty = true;
            return;
        }

        if (defined("IS_PHP")) {
            #ifndef KPHP
            $this->currentResponsesKeysIndex = (int) array_key_first($this->responsesKeys);
            #endif
        } else {
            $this->currentResponsesKeysIndex = (int)array_first_key($this->responsesKeys);
        }

        $this->currentResponseKey = $this->responsesKeys[$this->currentResponsesKeysIndex];
        $this->currentResponse = $this->getResponse($this->currentResponseKey);
        $this->isNewResponse = true;
    }

    /**
     * @throws Exception\TimeoutException
     * @throws TransportException
     */
    public function stream(): ?ChunkInterface
    {
        if ($this->isResponsesEmpty || $this->isEmpty) {
            return $this->finishResponses();
        }
        if ($this->isNewResponse) {
            $this->timeoutMax = $this->timeout ?? max($this->timeoutMax, $this->currentResponse->getTimeout());
            $this->timeoutMin = min($this->timeoutMin, $this->currentResponse->getTimeout(), 1);
            $this->chunk = null;

            if (isset($this->multi->handlesActivity[$this->currentResponseKey])) {
                // no-op
            } elseif (!isset($this->multi->openHandles[$this->currentResponseKey])) {
                unset($this->runningResponses[$this->currentRunningResponsesIndex]->responses[$this->currentResponseKey]);
                if ($this->hasNextResponse()) {
                    // getting an index and a value of the next $runningResponses[$currentRunningResponsesIndex]->responses array element
                    $this->nextResponse();
                    return $this->stream(); // start a new iteration with the next element
                } else {
                    return $this->finishResponses();
                }
            } elseif ($this->elapsedTimeout >= $this->timeoutMax) {
                $this->multi->handlesActivity[$this->currentResponseKey] = [
                    (new HandleActivity())->setChunk(
                        new ErrorChunk(
                            $this->currentResponse->getOffset(),
                            null,
                            sprintf('Idle timeout reached for "%s".', $this->currentResponse->getInfo('url'))
                        )
                    )
                ];
            } elseif ($this->hasNextResponse()) {
                    $this->nextResponse();
                    return $this->stream();
            } else {
                return $this->finishResponses();
            }
            $this->isNewResponse = false;
            return $this->stream();
        } elseif ($this->multi->handlesActivity[$this->currentResponseKey] ?? false) {
            return $this->getChunk($this->multi->handlesActivity[$this->currentResponseKey]);
        } else {
            unset($this->multi->handlesActivity[$this->currentResponseKey]);
            if ($this->chunk instanceof ErrorChunk) {
                if (!$this->chunk->didThrow()) {
                    // Ensure transport exceptions are always thrown
                    $this->chunk->getContent();
                }
            }
            if ($this->hasNextResponse()) {
                $this->nextResponse();
                $this->isNewResponse = true;
                return $this->stream();
            } else {
                return $this->finishResponses();
            }
        }
    }

    private function finishResponses(): ?ChunkInterface
    {
        if (!$this->isEmpty) {
            if (!$this->responses) {
                unset($this->runningResponses[$this->currentRunningResponsesIndex]);
            }
            $this->multi->handlesActivity = $this->multi->handlesActivity ?: [];
            $this->multi->openHandles = $this->multi->openHandles ?: [];
        }

        if ($this->hasNextRunningResponses()) {
            $this->nextRunningResponses();
            $this->multi = $this->runningResponses[$this->currentRunningResponsesIndex]->getMulti();
            CurlResponse::perform(
                $this->multi,
                $this->runningResponses[$this->currentRunningResponsesIndex]->responses,
                $this->currentResponseKey
            );
            $this->getResponsesOfRunningResponses();
            if ($this->isResponsesEmpty) {
                return $this->finishResponses();
            }
            $this->isNewResponse = true;
            if ($this->getResponse() === null) {
                return $this->finishResponses();
            } else {
                $this->currentResponse = $this->getResponse();
            }
        } else {
            if (!$this->runningResponses) {
                return null;
            }
            if (-1 === CurlResponse::select(
                    $this->multi,
                    min($this->timeoutMin, $this->timeoutMax - $this->elapsedTimeout)
                )) {
                usleep(min(500, $this->timeoutMin > 0 ? 1E6 * $this->timeoutMin : 10));
            }
            $this->elapsedTimeout = microtime(true) - $this->lastActivity;
        }
        return $this->stream();
    }

    /**
     * @param HandleActivity[] $handleActivity
     */
    public function getChunk(array &$handleActivity): ?ChunkInterface
    {
        switch ($this->functionIndex) {
            case 0:
                $this->hasActivity = true;
                $this->elapsedTimeout = 0;
                if (($tempChunk = array_shift($handleActivity)) === null) {
                    return null;
                } elseif ($stringChunk = $tempChunk->getActivityMessage()) {
                    if (
                        '' !== $stringChunk
                        && null !== $this->currentResponse->getContentParam()
                        && \strlen($stringChunk) !== fwrite($this->currentResponse->getContentParam(), $stringChunk)
                    ) {
                        $handleActivity = [
                            (new HandleActivity()),
                            (new HandleActivity())->setException(
                                new TransportException(
                                    sprintf(
                                        'Failed writing %d bytes to the response buffer.',
                                        \strlen($stringChunk)
                                    )
                                )
                            )
                        ];
                        $this->getChunk($handleActivity);
                    }
                    $chunkLen = \strlen($stringChunk);
                    $this->chunk = new DataChunk($this->currentResponse->getOffset(), $stringChunk);
                    $this->currentResponse->setOffset($this->currentResponse->getOffset() + $chunkLen);
                } elseif ($tempChunk->isNull()) {
                    /** @var Throwable $e */
                    $e = $handleActivity[0]->getActivityException() ?: $handleActivity[0]->getActivityError();
                    unset(
                        $this->runningResponses[$this->currentRunningResponsesIndex]->responses[$this->currentResponseKey],
                        $this->multi->handlesActivity[$this->currentResponseKey]
                    );
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
                    unset($this->responses[$this->currentResponseKey]);
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

            case (1):
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

    private function nextResponse(): void
    {
        if (!$this->responsesKeys) {
            $this->isEmpty = true;
            return;
        }
        ++$this->currentResponsesKeysIndex;
        $this->currentResponseKey = $this->responsesKeys[$this->currentResponsesKeysIndex];
        $this->currentResponse = $this->getResponse($this->currentResponseKey);
    }

    private function nextRunningResponses(): void
    {
        ++$this->currentRunningResponsesIndex;
    }

    private function getResponsesOfRunningResponses(): void
    {
        // moving to the next element of $runningResponses array means changing the following params

        if (defined('IS_PHP')) {
            #ifndef KPHP
            $this->responsesKeys = array_keys(
                $this->runningResponses[$this->currentRunningResponsesIndex]->responses
            ) ?: null;
            #endif
        } else {
            $this->responsesKeys = array_keys_as_ints(
                $this->runningResponses[$this->currentRunningResponsesIndex]->responses
            ) ?: null;
        }

        if ($this->responsesKeys) {
            $this->isResponsesEmpty = false;
        } else {
            return;
        }

        if (defined('IS_PHP')) {
            #ifndef KPHP
            $this->currentResponsesKeysIndex = (int)array_first_key($this->responsesKeys);
            #endif
        } else {
            $this->currentResponsesKeysIndex = (int) array_key_first($this->responsesKeys);
        }

        $this->currentResponseKey = $this->responsesKeys[$this->currentResponsesKeysIndex];
    }

    private function hasNextRunningResponses(): bool
    {
        if ($this->isEmpty) {
            return false;
        }
        return isset($this->runningResponses[$this->currentRunningResponsesIndex + 1]);
    }

    private function hasNextResponse(): bool
    {
        return isset($this->runningResponses[$this->currentRunningResponsesIndex]->responses[$this->responsesKeys[$this->currentResponsesKeysIndex + 1]]);
    }

    private function getResponse(?int $key = null): CurlResponse
    {
        if (!isset($this->currentRunningResponsesIndex)) {
            throw new InvalidArgumentException(
                sprintf(
                    "Attempt to get a CurlResponse object from an unset runningResponses array in a %s class\n",
                    self::class
                )
            );
        } elseif ($key === null) {
            if (!isset($this->currentResponseKey)) {
                throw new InvalidArgumentException(
                    sprintf(
                        "Attempt to get an undefined CurlResponse object from a runningResponses array in a %s class\n",
                        self::class
                    )
                );
            } else {
                $key = $this->currentResponseKey;
            }
        }
        if (isset($this->runningResponses[$this->currentRunningResponsesIndex]->responses[$key])) {
            return $this->runningResponses[$this->currentRunningResponsesIndex]->responses[$key];
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    "Attempt to get a non-existed CurlResponse object from a responses array in a %s class\n",
                    self::class
                )
            );
        }
    }

    public function hasResponses(): bool
    {
        return (bool)$this->runningResponses;
    }

}