let blockElements = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
CKEDITOR.stylesSet.add('bootstrap4styles', [
    { name: 'lead', element: 'p', attributes: { 'class': 'lead' } },
    { name: 'align left', element: blockElements, attributes: { 'class': 'text-left' } },
    { name: 'align right', element: blockElements, attributes: { 'class': 'text-right' } },
    { name: 'align center', element: blockElements, attributes: { 'class': 'text-center' } },
    { name: 'justify', element: blockElements, attributes: { 'class': 'text-justify' } },
    { name: 'small', element: 'small' },
    { name: 'button primary', element: 'a', attributes: { 'class': 'btn btn-primary' } },
    { name: 'button secondary', element: 'a', attributes: { 'class': 'btn btn-secondary' } },
    { name: 'button dark', element: 'a', attributes: { 'class': 'btn btn-dark' } },
    { name: 'button light', element: 'a', attributes: { 'class': 'btn btn-light' } },
    { name: 'button danger', element: 'a', attributes: { 'class': 'btn btn-danger' } },
    { name: 'button warning', element: 'a', attributes: { 'class': 'btn btn-warning' } },
    { name: 'button info', element: 'a', attributes: { 'class': 'btn btn-info' } },
    { name: 'button success', element: 'a', attributes: { 'class': 'btn btn-success' } }
]);
CKEDITOR.dtd.$removeEmpty.span = 0;
CKEDITOR.dtd.$removeEmpty.i = 0;
