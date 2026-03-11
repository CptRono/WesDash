// screen/OrderDetailsScreen.js
import React, { useState } from 'react';
import {
  View, Text, TextInput, TouchableOpacity,
  Button, Alert, StyleSheet, ScrollView
} from 'react-native';
import MapView, { Marker, PROVIDER_DEFAULT } from 'react-native-maps';
import { BASE_URL } from '../config';

export default function OrderDetailsScreen({ navigation, route }) {
  const { cart, username, role } = route.params;
  const [speed, setSpeed]   = useState('common');
  const [dropOff, setDrop]  = useState('');
  const [marker, setMarker] = useState(null);
  
  // Calculate total price
  const totalPrice = cart.reduce((sum, item) => {
    const itemPrice = item.items?.[0]?.price?.regular ? parseFloat(item.items[0].price.regular) : 0;
    return sum + itemPrice;
  }, 0).toFixed(2);

  const [region, setRegion] = useState({
    latitude: 41.5556,
    longitude:-72.6558,
    latitudeDelta: 0.02,
    longitudeDelta:0.02,
  });

  const handleMapPress = (e) => {
    const { latitude, longitude } = e.nativeEvent.coordinate;
    setMarker({ latitude, longitude });
    setDrop(`${latitude.toFixed(6)}, ${longitude.toFixed(6)}`);
  };

  const submit = async () => {
    if (!marker || !dropOff.trim()) {
      Alert.alert('Error','Please set a drop-off location by tapping the map.');
      return;
    }
    const payload = {
      items: cart.map(c=>({
        item: c.description,
        quantity: 1,
        product_id: c.productId || c.upc || '',
        price: c.items?.[0]?.price?.regular || '0.00'
      })),
      drop_off_location: dropOff,
      delivery_speed: speed,
      lat: marker.latitude,
      lng: marker.longitude,
      est_price: totalPrice
    };
    try {
      const r = await fetch(`${BASE_URL}/WesDashAPI/create_requests.php`,{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        credentials:'include',
        body:JSON.stringify(payload)
      });
      const d = await r.json();
      if (d.success) {
        Alert.alert('Success','Order submitted!',[
          {text:'OK',onPress:()=>navigation.navigate('ViewRequestScreen',{username,role})}
        ]);
      } else throw new Error(d.error||'Server error');
    } catch(e){ Alert.alert('Error',e.message); }
  };

  /* --------- UI --------- */
  return (
    <ScrollView contentContainerStyle={styles.container}>
      {/* Items */}
      <Text style={styles.header}>Your Items</Text>
      {cart.map((it,i)=>(
        <Text key={i} style={styles.itemTxt}>• {it.description} {it.items?.[0]?.price?.regular ? `- $${it.items[0].price.regular}` : ''}</Text>
      ))}
      
      <Text style={styles.totalPrice}>Total Price: ${totalPrice}</Text>

      {/* Speed */}
      <Text style={styles.label}>Delivery speed</Text>
      <View style={styles.speedRow}>
        {['urgent','common'].map(s=>(
          <TouchableOpacity key={s}
            style={[styles.speedBtn, speed===s && styles.speedSel]}
            onPress={()=>setSpeed(s)}>
            <Text style={speed===s?styles.speedTxtSel:styles.speedTxt}>
              {s.toUpperCase()}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      {/* Address */}
      <Text style={styles.label}>Drop-off (lat, lng)</Text>
      <TextInput
        style={styles.input}
        value={dropOff}
        onChangeText={setDrop}
        placeholder="Tap the map or type coordinates"
      />

      {/* Map */}
      <MapView
        style={styles.map}
        provider={PROVIDER_DEFAULT}
        region={region}
        onRegionChangeComplete={setRegion}
        onPress={handleMapPress}>
        {marker && <Marker coordinate={marker}/>}
      </MapView>

      <Button title="SUBMIT ORDER" onPress={submit}/>
    </ScrollView>
  );
}

/* --------- styles --------- */
const styles = StyleSheet.create({
  container:{ padding:16, backgroundColor:'#fff' },
  header:{ fontSize:20, fontWeight:'700', marginBottom:6 },
  itemTxt:{ fontSize:15, marginVertical:2 },
  totalPrice:{ fontSize:18, fontWeight:'700', color:'#2e8b57', marginTop:16, marginBottom:8 },

  label:{ fontSize:16, fontWeight:'600', marginTop:16, marginBottom:4 },
  input:{ borderWidth:1, borderColor:'#aaa', borderRadius:5, padding:8 },

  speedRow:{ flexDirection:'row', marginTop:4 },
  speedBtn:{ flex:1, padding:10, borderWidth:1, borderColor:'#777',
             alignItems:'center', marginHorizontal:4, borderRadius:4 },
  speedSel:{ backgroundColor:'#007bff', borderColor:'#007bff' },
  speedTxt:{ color:'#333' }, speedTxtSel:{ color:'#fff', fontWeight:'700' },

  map:{ width:'100%', height:220, borderRadius:6, marginTop:10, marginBottom:16 },
});
