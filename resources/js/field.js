import IndexField from './components/IndexField'
import DetailField from './components/DetailField'
import FormField from './components/FormField'
import MediaManager from './components/MediaManager'

Nova.booting((app, store) => {
  app.component('index-imagic', IndexField)
  app.component('detail-imagic', DetailField)
  app.component('form-imagic', FormField)
  app.component('imagic-media-manager', MediaManager)
})
