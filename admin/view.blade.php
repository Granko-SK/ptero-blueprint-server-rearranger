<div class="row">

  <div class="col-xs-12 col-md-6 col-lg-4">
    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title">
          Server order
        </h3>
      </div>
      <div class="box-body">

        <p>
          Go to <a href="/admin/servers">Servers</a> and drag a server with the handle (the six dots) next to its wrench icon.
          The same order is shown on the dashboard.
        </p>

        <p class="text-muted small">
          Servers that have not been placed yet (for example, new ones) appear after the ordered servers, in their usual order.
        </p>

        <p class="text-muted small">
          Servers in the saved order: {{ $count }}
        </p>

      </div>
      <div class="box-footer">
        <form method="POST" action="{{ route('admin.extensions.serverorder.post') }}">
          {{ csrf_field() }}
          <input type="hidden" name="action" value="reset">
          <button type="submit" class="btn btn-danger btn-sm">Reset order</button>
        </form>
      </div>
    </div>
  </div>

</div>
