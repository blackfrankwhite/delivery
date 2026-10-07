<script setup>
import {
  ArrowRight,
  Boxes,
  CalendarDays,
  ChartNoAxesColumnIncreasing,
  ChevronDown,
  ChevronUp,
  Circle,
  CircleAlert,
  CircleCheck,
  Clock3,
  GripVertical,
  Headset,
  Inbox,
  LayoutDashboard,
  LoaderCircle,
  LogOut,
  MapPin,
  Menu,
  Navigation,
  Package,
  PackageCheck,
  Pencil,
  Phone,
  Plus,
  RefreshCw,
  Route,
  Search,
  Sun,
  Trash2,
  Truck,
  UserRoundCheck,
  UserRoundCog,
  Users,
  X,
} from 'lucide-vue-next';
const icons = {
  ArrowRight,
  Boxes,
  CalendarDays,
  ChartNoAxesColumnIncreasing,
  ChevronDown,
  ChevronUp,
  Circle,
  CircleAlert,
  CircleCheck,
  Clock3,
  GripVertical,
  Headset,
  Inbox,
  LayoutDashboard,
  LoaderCircle,
  LogOut,
  MapPin,
  Menu,
  Navigation,
  Package,
  PackageCheck,
  Pencil,
  Phone,
  Plus,
  RefreshCw,
  Route,
  Search,
  Sun,
  Trash2,
  Truck,
  UserRoundCheck,
  UserRoundCog,
  Users,
  X,
};
import { computed } from 'vue';
const props = defineProps({
  name: { type: String, required: true },
  size: { type: Number, default: 18 },
});
const component = computed(() => icons[props.name] || icons.Circle);
</script>
<template>
  <component
    :is="component"
    :size="size"
    :stroke-width="1.7"
    aria-hidden="true"
  />
</template>
