<template>
  <div ref="mapContainer" class="event-map"></div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'

const VILLAHERMOSA_LAT = 17.9892
const VILLAHERMOSA_LNG = -92.9474

const props = defineProps({
  latitude: { type: Number, default: VILLAHERMOSA_LAT },
  longitude: { type: Number, default: VILLAHERMOSA_LNG },
  venueName: { type: String, default: '' },
})

const mapContainer = ref(null)
let mapInstance = null
let markerInstance = null

function coords() {
  const lat = props.latitude ?? VILLAHERMOSA_LAT
  const lng = props.longitude ?? VILLAHERMOSA_LNG
  return [lat, lng]
}

// Fix Leaflet's default icon path for bundlers
delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: markerIcon2x,
  iconUrl: markerIcon,
  shadowUrl: markerShadow,
})

function initMap() {
  if (!mapContainer.value) return
  if (mapInstance) {
    mapInstance.remove()
    mapInstance = null
  }

  const [lat, lng] = coords()

  mapInstance = L.map(mapContainer.value, {
    center: [lat, lng],
    zoom: 15,
    scrollWheelZoom: false,
    zoomControl: false,
  })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(mapInstance)

  markerInstance = L.marker([lat, lng])
    .addTo(mapInstance)

  if (props.venueName) {
    markerInstance.bindPopup(props.venueName)
  }
}

onMounted(() => {
  initMap()
})

watch(() => [props.latitude, props.longitude], () => {
  if (!mapInstance) return
  const [lat, lng] = coords()
  mapInstance.setView([lat, lng], 15)
  if (markerInstance) {
    markerInstance.setLatLng([lat, lng])
    if (props.venueName) {
      markerInstance.bindPopup(props.venueName)
    }
  }
})
</script>

<style scoped>
.event-map {
  width: 100%;
  height: 200px;
  border-radius: var(--radius-md);
  overflow: hidden;
}
</style>
