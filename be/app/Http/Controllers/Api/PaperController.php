<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaperRequest;
use App\Jobs\RenderClipPaper;
use App\Models\Frame;
use App\Models\Paper;
use App\Models\ShortCode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaperController extends Controller
{
    /**
     * Store a kiosk session: its rendered paper, its photos and the short code printed on it.
     * Each photo may come with a behind-the-scenes clip, sent under the photo's index. When
     * every slot of the session's frame has one, its clip paper is rendered on the queue.
     *
     * Kiosks retry uploads until they succeed, so a session that is already stored is
     * acknowledged without storing it again. A replacement code is reserved for each
     * code used, keeping the kiosk's pool topped up.
     */
    public function store(StorePaperRequest $request): JsonResponse
    {
        $ref = $request->string('ref')->toString();

        if (Paper::query()->where('ref', $ref)->exists()) {
            return $this->stored($ref, replacementCode: null, status: 200);
        }

        $diskName = config('filament.default_filesystem_disk');
        $disk = Storage::disk($diskName);

        $paperPath = $request->file('paper')->store('papers', $diskName);
        $photos = collect($request->file('photos'))
            ->map(fn (UploadedFile $photo, int $index): array => [
                'file_path' => $photo->store('photos', $diskName),
                'clip_path' => $request->file("clips.{$index}")?->store('clips', $diskName),
            ])
            ->all();
        $storedPaths = collect($photos)->flatten()->filter()->push($paperPath)->all();
        $frameId = Frame::query()->whereKey($request->integer('frame_id'))->value('id');

        try {
            /** @var array{Paper, ?string} $stored */
            $stored = DB::transaction(function () use ($request, $ref, $paperPath, $photos, $frameId): array {
                $paper = Paper::query()->create(['file_path' => $paperPath, 'ref' => $ref, 'frame_id' => $frameId]);

                $paper->photos()->createMany($photos);

                if (! $request->filled('code')) {
                    return [$paper, null];
                }

                $isCodeClaimed = ShortCode::query()
                    ->where('code', $request->string('code')->toString())
                    ->whereNull('paper_id')
                    ->update(['paper_id' => $paper->id]) === 1;

                if (! $isCodeClaimed) {
                    throw ValidationException::withMessages(['code' => 'The code is not reserved or has already been used.']);
                }

                return [$paper, ShortCode::reserve(1)->sole()->code];
            });
        } catch (UniqueConstraintViolationException) {
            $disk->delete($storedPaths);

            return $this->stored($ref, replacementCode: null, status: 200);
        } catch (Throwable $exception) {
            $disk->delete($storedPaths);

            throw $exception;
        }

        [$paper, $replacementCode] = $stored;

        // A clip paper that fails to render or queue never fails the upload, which is already
        // stored; on the sync queue it renders right here. The admin can render it again.
        if ($paper->canRenderClipPaper()) {
            // A block, not an arrow function: a pending dispatch is only sent once it is
            // destructed, which has to happen inside `rescue`.
            rescue(function () use ($paper): void {
                RenderClipPaper::dispatch($paper);
            });
        }

        return $this->stored($ref, $replacementCode, status: 201);
    }

    private function stored(string $ref, ?string $replacementCode, int $status): JsonResponse
    {
        return response()->json([
            'data' => [
                'ref' => $ref,
                'replacement_code' => $replacementCode,
            ],
        ], $status);
    }
}
