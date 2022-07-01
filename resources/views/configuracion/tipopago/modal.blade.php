<form action="{{route('tipopagos.destroy',$id)}}" method="POST">
{{method_field('delete')}}
{{csrf_field()}}
  <div class="modal fade modal-slide-in-right" aria-hidden="true" role="dialog" tabindex="-1" id="modal-delete{{$id}}">
    <div class="modal-dialog">
      <div class="modal-content bg-danger">
        <div class="modal-header">
        <h4 class="modal-title">Confirmar si desea aplicar acción!</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hiden="true">x</span>
        </button>
        </div>

        <div class="modal-body">
          @if($condicion_aux == 1)
            <h5 style="text-align: center;" class="modal-title">Desea inhabilitar este tipo de pago?<br> {{$nombrepago}}</h5>
          @else
            <h5 style="text-align: center;" class="modal-title">Desea habilitar este tipo de pago?<br>{{$nombrepago}}</h5>
          @endif
        </div>

        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-outline-light" data-dismiss="modal">cerrar</button>
          <button type="submit" class="btn btn-outline-light">Confirmar</button>
        </div>
      </div>
    </div>
  </div>
</form>



