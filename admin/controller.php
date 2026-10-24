<?php

namespace Pterodactyl\Http\Controllers\Admin\Extensions\serverorder;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Admin\BlueprintAdminLibrary as BlueprintExtensionLibrary;

class serverorderExtensionController extends Controller
{
  private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

  public function __construct(
    private ViewFactory $view,
    private BlueprintExtensionLibrary $blueprint,
  ) {}

  /**
   * GET /admin/extensions/serverorder
   *
   * Shows the settings page. With ?format=json it returns the saved order instead,
   * which the Servers page script uses to sort its rows.
   */
  public function index(Request $request): View|JsonResponse
  {
    if ($request->query('format') === 'json') {
      return response()->json(['order' => $this->storedOrder()]);
    }

    // Blueprint's admin page template reads $blueprint (for the header and the
    // settings dialog), and $root, so both must be passed in or the page throws a 500.
    return $this->view->make('admin.extensions.serverorder.index', [
      'blueprint' => $this->blueprint,
      'root' => '/admin/extensions/serverorder',
      'count' => count($this->storedOrder()),
    ]);
  }

  /**
   * POST /admin/extensions/serverorder
   *
   * JSON body { "visible": [uuid, ...] } saves the order of the servers currently
   * shown on the Servers page. A form field action=reset clears the saved order.
   */
  public function post(Request $request): JsonResponse|RedirectResponse
  {
    if ($request->input('action') === 'reset') {
      $this->blueprint->dbForget('serverorder', 'order');

      return redirect()->route('admin.extensions.serverorder.index');
    }

    $visible = $request->input('visible');
    if (!is_array($visible)) {
      return response()->json(['error' => 'Expected a list of server UUIDs.'], 422);
    }

    $visible = array_map(
      fn ($uuid) => is_string($uuid) ? strtolower(trim($uuid)) : '',
      $visible
    );
    $visible = array_values(array_unique(array_filter(
      $visible,
      fn ($uuid) => preg_match(self::UUID_PATTERN, $uuid) === 1
    )));

    $merged = $this->mergeOrder($this->storedOrder(), $visible);
    $this->blueprint->dbSet('serverorder', 'order', $merged);

    return response()->json(['saved' => count($merged)]);
  }

  /**
   * The saved order: a list of server UUIDs, first to last.
   */
  private function storedOrder(): array
  {
    $stored = $this->blueprint->dbGet('serverorder', 'order', []);

    return is_array($stored) ? array_values(array_filter($stored, 'is_string')) : [];
  }

  /**
   * Puts the visible servers into the saved order as one block.
   *
   * The block takes the place of the first visible server that was already saved,
   * so servers on other admin pages keep their relative order. Visible servers that
   * weren't saved yet are added in the block; if none were saved, the block goes at the end.
   */
  private function mergeOrder(array $stored, array $visible): array
  {
    $isVisible = array_flip($visible);

    $firstSlot = null;
    foreach ($stored as $index => $uuid) {
      if (isset($isVisible[$uuid])) {
        $firstSlot = $index;
        break;
      }
    }

    // Everything before $firstSlot is not visible, so its indexes stay the same in $rest.
    $rest = array_values(array_filter($stored, fn ($uuid) => !isset($isVisible[$uuid])));
    $insertAt = $firstSlot ?? count($rest);

    array_splice($rest, $insertAt, 0, $visible);

    return $rest;
  }
}
