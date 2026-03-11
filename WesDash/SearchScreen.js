import React, { useState } from 'react';
import {
  View, Text, TextInput, Button, StyleSheet, FlatList,
  ActivityIndicator, Image, Alert, TouchableOpacity
} from 'react-native';
import { BASE_URL } from '../config';

export default function SearchScreen({ navigation, route }) {
  const { username, role } = route.params || {};
  const [query, setQuery]    = useState('');
  const [results, setResults]= useState([]);
  const [loading, setLoading]= useState(false);
  const [cart, setCart]      = useState([]);
  const [tapLock, setTapLock]= useState(false);      // 防止连点

  /* ---------- search ---------- */
  const handleSearch = async () => {
    if (!query.trim()) return;
    setLoading(true);
    try {
      const url = `${BASE_URL}/WesDashAPI/products.php?term=${encodeURIComponent(
        query
      )}&fulfillment=aisle&locationId=01100002&show=items.price`;
      // print url
      console.log('URL:', url);
      const raw  = await (await fetch(url)).text();
      const json = JSON.parse(raw.slice(raw.indexOf('{')));
      setResults(json.data || []);
    } catch (e) {
      Alert.alert('Error', e.message);
    } finally { setLoading(false); }
  };

  /* ---------- add to cart ---------- */
  const addToCart = (item) => {
    if (tapLock) return;
    setTapLock(true);
    setCart(prev => [...prev, item]);
    Alert.alert('Added', `${item.description} added to cart`);
    setTimeout(()=>setTapLock(false),150);
  };

  const imgUrl = (itm)=>{
    if(!itm.images?.length) return null;
    const pick = itm.images.find(i=>i.featured||i.perspective==='front')||itm.images[0];
    return pick.sizes?.[0]?.url ?? null;
  };

  const renderItem = ({ item }) => (
    <TouchableOpacity
      style={styles.card}
      activeOpacity={0.7}
      onPress={() => addToCart(item)}
    >
      {imgUrl(item) ? (
        <Image source={{ uri: imgUrl(item) }} style={styles.thumb} />
      ) : (
        <View style={[styles.thumb, { backgroundColor: '#ddd' }]} />
      )}
      <View style={styles.info}>
        {/* Ensure price is wrapped in a <Text> */}
        <Text style={styles.title}>
          {item.items?.[0]?.price?.regular
            ? `$${item.items[0].price.regular}`
            : 'Price not available'}
        </Text>
        <Text style={styles.subtitle}>{item.brand}</Text>
        {item.items?.[0]?.size && (
          <Text style={styles.size}>{item.items[0].size}</Text>
        )}
        <Text style={styles.addText}>Tap to add to cart →</Text>
      </View>
    </TouchableOpacity>
  );

  /* ---------- nav helpers ---------- */
  const goCheckout   = () => navigation.navigate('Checkout',   { cart, username, role });
  const goCustom     = () => navigation.navigate('CustomOrder',{ username, role });

  /* ---------- UI ---------- */
  return (
    <View style={styles.container}>

      {/* 说明文字 */}
      <Text style={styles.h1}>Enter the product you want to buy.</Text>
      <Text style={styles.h2}>
        If it isn’t listed <Text style={{fontWeight:'400'}}>or you want to specify the store</Text>,{'\n'}
        you can create a custom order.
      </Text>

      {/* 创建自定义订单 */}
      <TouchableOpacity style={styles.customBtn} onPress={goCustom} activeOpacity={0.85}>
        <Text style={styles.customTxt}>CREATE CUSTOM ORDER</Text>
      </TouchableOpacity>

      {/* 搜索区域 */}
      <View style={styles.searchBar}>
        <TextInput
          style={styles.input}
          placeholder="Search products..."
          value={query}
          onChangeText={setQuery}
        />
        <Button title="Search" onPress={handleSearch}/>
      </View>

      {/* 结果 / 加载 */}
      {loading ? (
        <ActivityIndicator size="large" style={{ marginTop:20 }}/>
      ) : (
        <FlatList
          data={results}
          keyExtractor={(it,idx)=>it.productId||it.upc||`${idx}`}
          renderItem={renderItem}
          contentContainerStyle={styles.list}
          ListEmptyComponent={<Text style={styles.emptyTxt}>No products found.</Text>}
        />
      )}

      {/* 购物车 */}
      {cart.length>0 && (
        <TouchableOpacity style={styles.checkoutBtn} onPress={goCheckout} activeOpacity={0.85}>
          <Text style={styles.checkoutTxt}>Checkout ({cart.length})</Text>
        </TouchableOpacity>
      )}
    </View>
  );
}

/* ---------- styles ---------- */
const styles = StyleSheet.create({
  container:{ flex:1, backgroundColor:'#fff' },

  h1:{ fontSize:18, fontWeight:'700', textAlign:'center', marginTop:12 },
  h2:{ fontSize:16, fontWeight:'700', textAlign:'center', marginTop:4, marginBottom:10 },

  /* 自定义订单按钮 */
  customBtn:{
    alignSelf:'center',
    backgroundColor:'#673ab7',
    borderRadius:24,
    paddingHorizontal:24,
    paddingVertical:10,
    marginBottom:12,
    elevation:3
  },
  customTxt:{ color:'#fff', fontSize:15, fontWeight:'700' },

  searchBar:{ flexDirection:'row', paddingHorizontal:10, paddingBottom:8 },
  input:{ flex:1, borderWidth:1, borderColor:'#ccc', borderRadius:4,
          marginRight:10, paddingHorizontal:8 },

  list:{ padding:10 },
  card:{ flexDirection:'row', backgroundColor:'#f8f8f8', borderRadius:8,
         marginBottom:15, overflow:'hidden', elevation:2 },
  thumb:{ width:100, height:100 },
  info:{ flex:1, padding:12 },
  title:{ fontSize:16, fontWeight:'bold' },
  subtitle:{ fontSize:14, color:'#555', marginTop:2 },
  size:{ fontSize:13, color:'#777', fontStyle:'italic', marginTop:2 },
  addText:{ fontSize:13, color:'#3498db', marginTop:8, fontWeight:'500' },

  emptyTxt:{ textAlign:'center', marginTop:40, color:'#666' },

  checkoutBtn:{ position:'absolute', right:25, bottom:25,
                backgroundColor:'#e91e63', borderRadius:28,
                paddingHorizontal:20, paddingVertical:12, elevation:4 },
  checkoutTxt:{ color:'#fff', fontSize:16, fontWeight:'700' },
});
