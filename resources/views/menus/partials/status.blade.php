@if($status['success'] ?? null)
    <x-forms::alert type="success" role="status">{{ $status['success'] }}</x-forms::alert>
@endif

@if($status['error'] ?? null)
    <x-forms::alert type="danger" role="alert">{{ $status['error'] }}</x-forms::alert>
@endif
